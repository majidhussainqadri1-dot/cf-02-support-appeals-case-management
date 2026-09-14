from pathlib import Path

p=Path('src/Infrastructure/WordPress/OperationsRepository.php')
s=p.read_text()

old="""        $this->transaction(function () use ($appealId, $caseId, $context, $originalDecisionRef, $policyVersion, $evidenceRefs, $submissions, $originalHash, $dossierHash, $idempotencyKey, $at): void {
            $ok = $this->wpdb->insert($this->tables['appeals'], [
"""
new="""        $this->transaction(function () use ($appealId, $caseId, $context, $originalDecisionRef, $policyVersion, $evidenceRefs, $submissions, $originalHash, $dossierHash, $idempotencyKey, $at): void {
            $this->lockCaseForLifecycle($caseId->value(), ['resolved','closed']);
            $ok = $this->wpdb->insert($this->tables['appeals'], [
"""
assert old in s
s=s.replace(old,new,1)

old="""        $this->transaction(function () use ($id, $caseId, $context, $reason, $authorityRef, $reviewDue, $idempotencyKey, $at): void {
            $ok = $this->wpdb->insert($this->tables['holds'], [
"""
new="""        $this->transaction(function () use ($id, $caseId, $context, $reason, $authorityRef, $reviewDue, $idempotencyKey, $at): void {
            $this->lockCaseForLifecycle($caseId->value());
            $ok = $this->wpdb->insert($this->tables['holds'], [
"""
assert old in s
s=s.replace(old,new,1)

old="""    public function purgeCase(string $caseId, array $providerResults, DateTimeImmutable $at): void
    {
        $activeHolds = (int) $this->value($this->wpdb->prepare(
            \"SELECT COUNT(*) FROM {$this->tables['holds']} WHERE case_uuid=%s AND state='active'\", $caseId
        ));
        if ($activeHolds > 0) {
            throw new RuntimeException('Active legal or appeal hold blocks purge.');
        }
        $unresolvedAppeals = (int) $this->value($this->wpdb->prepare(
            \"SELECT COUNT(*) FROM {$this->tables['appeals']} WHERE case_uuid=%s AND state<>'closed'\", $caseId
        ));
        if ($unresolvedAppeals > 0) {
            throw new RuntimeException('Open or unresolved appeal blocks purge until appeal closure.');
        }
        if (($providerResults['all_targets_reconciled'] ?? false) !== true) {
            throw new RuntimeException('Provider/cache/search deletion reconciliation is incomplete.');
        }
        $this->transaction(function () use ($caseId): void {
            $appeals = $this->rows($this->wpdb->prepare(\"SELECT appeal_uuid FROM {$this->tables['appeals']} WHERE case_uuid=%s\", $caseId));
"""
new="""    public function purgeCase(string $caseId, array $providerResults, DateTimeImmutable $at): void
    {
        if (($providerResults['all_targets_reconciled'] ?? false) !== true) {
            throw new RuntimeException('Provider/cache/search deletion reconciliation is incomplete.');
        }
        $this->transaction(function () use ($caseId): void {
            $this->lockCaseForLifecycle($caseId, ['closed']);
            $activeHolds = (int) $this->value($this->wpdb->prepare(
                \"SELECT COUNT(*) FROM {$this->tables['holds']} WHERE case_uuid=%s AND state='active'\", $caseId
            ));
            if ($activeHolds > 0) {
                throw new RuntimeException('Active legal or appeal hold blocks purge.');
            }
            $unresolvedAppeals = (int) $this->value($this->wpdb->prepare(
                \"SELECT COUNT(*) FROM {$this->tables['appeals']} WHERE case_uuid=%s AND state<>'closed'\", $caseId
            ));
            if ($unresolvedAppeals > 0) {
                throw new RuntimeException('Open or unresolved appeal blocks purge until appeal closure.');
            }
            $appeals = $this->rows($this->wpdb->prepare(\"SELECT appeal_uuid FROM {$this->tables['appeals']} WHERE case_uuid=%s\", $caseId));
"""
assert old in s
s=s.replace(old,new,1)

# Insert shared serialization helper immediately before purgeCase.
needle="""    public function purgeCase(string $caseId, array $providerResults, DateTimeImmutable $at): void
"""
helper="""    /** @param list<string> $allowedStates */
    private function lockCaseForLifecycle(string $caseId, array $allowedStates = []): array
    {
        $row = $this->row($this->wpdb->prepare(
            \"SELECT case_uuid,state,record_version FROM {$this->tables['cases']} WHERE case_uuid=%s FOR UPDATE\",
            $caseId
        ));
        if ($row === null) {
            throw new RuntimeException('Canonical case is unavailable for lifecycle serialization.');
        }
        if ($allowedStates !== [] && !in_array((string) $row['state'], $allowedStates, true)) {
            throw new RuntimeException('Canonical case state changed before lifecycle operation.');
        }
        return $row;
    }

"""
assert needle in s
s=s.replace(needle,helper+needle,1)
p.write_text(s)

t=Path('tests/c2n-fresh40.php')
x=t.read_text()
marker='\nif($failures!==[]){fwrite(STDERR,implode("\\n",$failures)."\\n");exit(1);}fwrite(STDOUT,"CF-02 fresh 40-round regression register passed through round 22.\\n");'
assert marker in x
add=r'''

$test(23,'retention purge is serialized with hold and appeal creation under the canonical case row lock',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo, 'lockCaseForLifecycle'));
    assert(str_contains($repo, 'FOR UPDATE'));
    assert(substr_count($repo, 'lockCaseForLifecycle($caseId->value()') >= 2);
    assert(str_contains($repo, "lockCaseForLifecycle($caseId, ['closed'])"));
    $purge=strpos($repo, 'public function purgeCase');
    $lock=strpos($repo, "lockCaseForLifecycle($caseId, ['closed'])", $purge);
    $hold=strpos($repo, "Active legal or appeal hold blocks purge.", $purge);
    $appeal=strpos($repo, "Open or unresolved appeal blocks purge until appeal closure.", $purge);
    assert($lock !== false && $hold !== false && $appeal !== false && $lock < $hold && $lock < $appeal);
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}fwrite(STDOUT,"CF-02 fresh 40-round regression register passed through round 23.\n");'''
x=x.replace(marker,add)
t.write_text(x)
