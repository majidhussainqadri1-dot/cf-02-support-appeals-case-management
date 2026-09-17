from pathlib import Path

ROOT=Path('.')
def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)
def once(s,a,b,label):
    if a not in s: raise SystemExit(f'R35 expected block missing: {label}')
    return s.replace(a,b,1)

# ------------------------------------------------------------------
# Runtime appeal law: a rejected appeal must be closable after notice.
# ------------------------------------------------------------------
policy_path='src/Application/RuntimeWorkflowPolicy.php'
policy=read(policy_path)
policy=once(policy,"        'rejected' => ['reopened'],","        'rejected' => ['closed', 'reopened'],",'rejected appeal closure')
write(policy_path,policy)

# ------------------------------------------------------------------
# Repository: server-derived case resolution gates and verified config approvals.
# ------------------------------------------------------------------
repo_path='src/Infrastructure/WordPress/OperationsRepository.php'
repo=read(repo_path)
marker='''    /** @return array<string,mixed> */
    public function createAttachment(
'''
method='''    public function assertCaseResolutionReady(SupportCaseId $caseId, string $nativeOutcomeRef = ''): void
    {
        $case = $this->row($this->wpdb->prepare(
            "SELECT case_uuid,state FROM {$this->tables['cases']} WHERE case_uuid=%s LIMIT 1",
            $caseId->value()
        ));
        if ($case === null || !in_array((string) $case['state'], ['in_progress','waiting_user','waiting_provider','reopened'], true)) {
            throw new RuntimeException('Case is not eligible for governed resolution.');
        }
        $openTasks = (int) $this->value($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['tasks']} WHERE case_uuid=%s AND state<>'completed'",
            $caseId->value()
        ));
        if ($openTasks > 0) {
            throw new RuntimeException('Case has open tasks or blockers.');
        }
        $openAppeals = (int) $this->value($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['appeals']} WHERE case_uuid=%s AND state<>'closed'",
            $caseId->value()
        ));
        if ($openAppeals > 0) {
            throw new RuntimeException('Case has an open or unresolved appeal.');
        }
        $commands = (int) $this->value($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['commands']} WHERE case_uuid=%s",
            $caseId->value()
        ));
        $unreconciled = (int) $this->value($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['commands']} WHERE case_uuid=%s AND state<>'succeeded'",
            $caseId->value()
        ));
        if ($unreconciled > 0) {
            throw new RuntimeException('A native-owner command failed or remains unreconciled.');
        }
        if ($commands > 0) {
            if (trim($nativeOutcomeRef) === '') {
                throw new RuntimeException('Resolved native-owner work requires an authoritative outcome reference.');
            }
            $matched = (int) $this->value($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['commands']} WHERE case_uuid=%s AND state='succeeded' AND outcome_ref=%s",
                $caseId->value(), $nativeOutcomeRef
            ));
            if ($matched < 1) {
                throw new RuntimeException('Native outcome reference is not reconciled to a succeeded command.');
            }
        } elseif (trim($nativeOutcomeRef) !== '') {
            throw new RuntimeException('Native outcome reference has no matching command evidence.');
        }
    }

'''
repo=once(repo,marker,method+marker,'resolution gate insertion')

# Insert a configuration approval verifier helper immediately before stageConfiguration.
marker='''    /** @return array<string,mixed> */
    public function stageConfiguration(PrincipalContext $context, string $key, array $config, array $approvals, string $idempotencyKey, DateTimeImmutable $at): array
'''
helper='''    /** @param list<mixed> $approvalRefs @return list<string> */
    private function verifiedConfigurationApprovals(array $approvalRefs, string $key, string $checksum, string $operation, PrincipalContext $context): array
    {
        $refs = array_values(array_unique(array_filter($approvalRefs, static fn (mixed $ref): bool => is_string($ref) && preg_match('/^[A-Za-z0-9._:\\/-]{8,191}$/', $ref) === 1)));
        if (count($refs) < 2) {
            throw new RuntimeException('Two independent governed configuration approvals are required.');
        }
        $approvers = [];
        foreach ($refs as $ref) {
            /** @var mixed $verification */
            $verification = apply_filters('cf02_verify_configuration_approval', null, [
                'approval_ref' => $ref, 'config_key' => $key, 'checksum' => $checksum,
                'operation' => $operation, 'actor_ref' => $context->actorReference(),
            ]);
            if (!is_array($verification) || ($verification['verified'] ?? false) !== true
                || !is_string($verification['approval_ref'] ?? null) || !hash_equals($ref, (string) $verification['approval_ref'])
                || !is_string($verification['config_key'] ?? null) || !hash_equals($key, (string) $verification['config_key'])
                || !is_string($verification['checksum'] ?? null) || !hash_equals($checksum, (string) $verification['checksum'])
                || !is_string($verification['approver_ref'] ?? null) || trim((string) $verification['approver_ref']) === '') {
                throw new RuntimeException('Configuration approval evidence is unavailable, invalid or drifted.');
            }
            $approver = (string) $verification['approver_ref'];
            if (hash_equals($approver, $context->actorReference())) {
                throw new RuntimeException('Configuration actor cannot self-approve governed evidence.');
            }
            $approvers[$approver] = true;
        }
        if (count($approvers) < 2) {
            throw new RuntimeException('Configuration approvals must come from two independent approvers.');
        }
        return $refs;
    }

'''
repo=once(repo,marker,helper+marker,'configuration approval helper')

# Stage only verified approval references bound to exact checksum.
old="""        $json = $this->json($config);
        $checksum = hash('sha256', $json);
        $existing = $this->row($this->wpdb->prepare(
"""
new="""        $json = $this->json($config);
        $checksum = hash('sha256', $json);
        $approvals = $this->verifiedConfigurationApprovals($approvals, $key, $checksum, 'stage', $context);
        $existing = $this->row($this->wpdb->prepare(
"""
repo=once(repo,old,new,'stage approval verification')

# Activation re-verifies the exact immutable snapshot approval evidence.
old="""        $approvals = json_decode((string) $row['approvals_json'], true);
        $approved = is_array($approvals) ? array_values(array_unique(array_filter($approvals, 'is_string'))) : [];
        if (count($approved) < 2 || !in_array($approvalRef, $approved, true) || hash_equals((string) $row['created_by'], $context->actorReference())) {
            throw new RuntimeException('Activation requires two independent approvals and separation of duties.');
        }
"""
new="""        $approvals = json_decode((string) $row['approvals_json'], true);
        $approved = $this->verifiedConfigurationApprovals(is_array($approvals) ? $approvals : [], $key, (string) $row['checksum'], 'activate', $context);
        if (!in_array($approvalRef, $approved, true) || hash_equals((string) $row['created_by'], $context->actorReference())) {
            throw new RuntimeException('Activation requires verified independent approvals and separation of duties.');
        }
"""
repo=once(repo,old,new,'activation approval verification')

# Rollback re-verifies approval evidence; stored strings are not themselves authority.
old="""        $approvals = json_decode((string) $row['approvals_json'], true);
        $approved = is_array($approvals) ? array_values(array_unique(array_filter($approvals, 'is_string'))) : [];
        if (count($approved) < 2 || !in_array($approvalRef, $approved, true)) {
            throw new RuntimeException('Rollback requires the approved snapshot evidence.');
        }
"""
new="""        $approvals = json_decode((string) $row['approvals_json'], true);
        $approved = $this->verifiedConfigurationApprovals(is_array($approvals) ? $approvals : [], $key, (string) $row['checksum'], 'rollback', $context);
        if (!in_array($approvalRef, $approved, true)) {
            throw new RuntimeException('Rollback requires verified approved snapshot evidence.');
        }
"""
repo=once(repo,old,new,'rollback approval verification')
write(repo_path,repo)

# ------------------------------------------------------------------
# REST controller: remove client-authored governance booleans as authority.
# ------------------------------------------------------------------
ctl_path='src/Infrastructure/WordPress/ComprehensiveRestController.php'
ctl=read(ctl_path)

old="""            if ($resolutionCode === '' || $instructions === '' || ((bool) $request->get_param('native_action_required') && $nativeRef === '')) {
                throw new RuntimeException('Resolution requires a code, user instructions and any required native outcome.');
            }
            $caseId = $this->caseId($request);
            $key = RequestGuard::idempotencyKey($request);
            $resolved = $this->operations->mutateCase(
"""
new="""            if ($resolutionCode === '' || $instructions === '') {
                throw new RuntimeException('Resolution requires a code and user instructions.');
            }
            $caseId = $this->caseId($request);
            $this->operations->assertCaseResolutionReady($caseId, $nativeRef);
            $key = RequestGuard::idempotencyKey($request);
            $resolved = $this->operations->mutateCase(
"""
ctl=once(ctl,old,new,'runtime resolution readiness')
ctl=once(ctl,"['resolution_code' => $resolutionCode, 'native_outcome_ref' => $nativeRef, 'verified' => (bool) $request->get_param('verified'), 'closure_notice_sent' => (bool) $request->get_param('closure_notice_sent')],","['resolution_code' => $resolutionCode, 'native_outcome_ref' => $nativeRef, 'verified' => true, 'verification_source' => 'server_runtime_gates'],",'server resolution evidence')

old="""            $userConfirmed = (bool) $request->get_param('user_confirmed');
            if (!$userConfirmed) {
                $deliveryStatus = $this->operations->outcomeDeliveryStatus($this->caseId($request));
                if ($deliveryStatus !== 'sent') {
                    throw new RuntimeException('Automatic closure requires confirmed outcome-notification delivery.');
                }
            }
            return $this->operations->mutateCase(
"""
new="""            $userConfirmed = (bool) $request->get_param('user_confirmed');
            $confirmationRef = '';
            if ($userConfirmed) {
                /** @var mixed $confirmation */
                $confirmation = apply_filters('cf02_verify_case_user_confirmation', null, [
                    'case_id' => (string) $case['case_uuid'], 'requester_ref' => (string) $case['requester_ref'],
                    'actor_ref' => $context->actorReference(),
                ]);
                if (!is_array($confirmation) || ($confirmation['verified'] ?? false) !== true
                    || !is_string($confirmation['requester_ref'] ?? null)
                    || !hash_equals((string) $case['requester_ref'], (string) $confirmation['requester_ref'])
                    || !is_string($confirmation['confirmation_ref'] ?? null)
                    || trim((string) $confirmation['confirmation_ref']) === '') {
                    throw new RuntimeException('User-confirmed closure requires authoritative requester confirmation evidence.');
                }
                $confirmationRef = (string) $confirmation['confirmation_ref'];
            } else {
                $deliveryStatus = $this->operations->outcomeDeliveryStatus($this->caseId($request));
                if ($deliveryStatus !== 'sent') {
                    throw new RuntimeException('Automatic closure requires confirmed outcome-notification delivery.');
                }
            }
            return $this->operations->mutateCase(
"""
ctl=once(ctl,old,new,'case closure confirmation verification')
ctl=once(ctl,"['user_confirmed' => $userConfirmed, 'outcome_delivery_status' => $userConfirmed ? 'confirmed_by_user' : 'sent'],","['user_confirmed' => $userConfirmed, 'confirmation_ref' => $confirmationRef, 'outcome_delivery_status' => $userConfirmed ? 'confirmed_by_user' : 'sent'],",'case closure evidence payload')

# Appeal closure: allow rejection closure without native implementation, but require authoritative delivered notice for every closure.
old="""            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'closed');
            if (trim((string) $appeal['implementation_ref']) === '') {
                throw new RuntimeException('Appeal cannot close before native implementation reconciliation.');
            }
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'closed'], 'AppealClosed', 'appeal_closure', RequestGuard::idempotencyKey($request), ['notice_sent' => (bool) $request->get_param('notice_sent')], $this->now());
"""
new="""            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'closed');
            if ((string) $appeal['state'] !== 'rejected' && trim((string) $appeal['implementation_ref']) === '') {
                throw new RuntimeException('Appeal cannot close before native implementation reconciliation.');
            }
            /** @var mixed $notice */
            $notice = apply_filters('cf02_verify_appeal_notice_delivery', null, [
                'appeal_id' => (string) $appeal['appeal_uuid'], 'case_id' => (string) $appeal['case_uuid'],
                'appellant_ref' => (string) $appeal['appellant_ref'], 'state' => (string) $appeal['state'],
            ]);
            if (!is_array($notice) || ($notice['delivered'] ?? false) !== true
                || !is_string($notice['notice_ref'] ?? null) || trim((string) $notice['notice_ref']) === '') {
                throw new RuntimeException('Appeal closure requires authoritative delivered-notice evidence.');
            }
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'closed'], 'AppealClosed', 'appeal_closure', RequestGuard::idempotencyKey($request), ['notice_ref' => (string) $notice['notice_ref'], 'notice_delivered' => true], $this->now());
"""
ctl=once(ctl,old,new,'appeal closure notice verification')
write(ctl_path,ctl)

# Regression coverage.
test_path='tests/c2n-r24-r33.php'
test=read(test_path)
marker='\nif($failures!==[]){'
addition=r'''
$test(35,'resolution closure appeals and configuration approvals rely on server evidence rather than client claims',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $ctl=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    $policy=$read('src/Application/RuntimeWorkflowPolicy.php');
    assert(str_contains($repo,'public function assertCaseResolutionReady'));
    assert(str_contains($repo,"state<>'completed'"));
    assert(str_contains($repo,"state<>'succeeded'"));
    assert(str_contains($repo,"state<>'closed'"));
    assert(str_contains($ctl,'assertCaseResolutionReady($caseId, $nativeRef)'));
    assert(!str_contains($ctl,"'verified' => (bool) $request->get_param('verified')"));
    assert(str_contains($ctl,'cf02_verify_case_user_confirmation'));
    assert(str_contains($ctl,'cf02_verify_appeal_notice_delivery'));
    assert(str_contains($policy,"'rejected' => ['closed', 'reopened']"));
    assert(str_contains($repo,'cf02_verify_configuration_approval'));
    assert(str_contains($repo,'Configuration approvals must come from two independent approvers.'));
});
'''
if "$test(35,'resolution closure appeals" not in test:
    test=test.replace(marker,addition+marker,1)
test=test.replace('CF-02 cumulative regression register passed through R34.','CF-02 cumulative regression register passed through R35.',1)
write(test_path,test)
print('R35 corrections materialized')
