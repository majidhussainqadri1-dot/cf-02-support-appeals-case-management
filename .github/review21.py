from pathlib import Path

p = Path('src/Infrastructure/WordPress/ComprehensiveRestController.php')
s = p.read_text()

old = """            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'implemented');
            $ref = sanitize_text_field((string) $request->get_param('implementation_ref'));
            if ($ref === '' || !(bool) $request->get_param('native_version_matches')) {
                throw new RuntimeException('Native implementation evidence is incomplete or drifted.');
            }
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'implemented','implementation_ref' => $ref], 'AppealImplemented', 'appeal_implementation', RequestGuard::idempotencyKey($request), ['implementation_ref' => $ref], $this->now());
"""
new = """            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'implemented');
            $ref = sanitize_text_field((string) $request->get_param('implementation_ref'));
            if ($ref === '') {
                throw new RuntimeException('Native implementation evidence is incomplete or drifted.');
            }
            /** @var mixed $verification */
            $verification = apply_filters('cf02_verify_appeal_implementation', null, [
                'appeal_id' => (string) $appeal['appeal_uuid'],
                'case_id' => (string) $appeal['case_uuid'],
                'native_command_ref' => is_string($appeal['native_command_ref'] ?? null) ? (string) $appeal['native_command_ref'] : null,
                'requested_implementation_ref' => $ref,
                'actor_ref' => $context->actorReference(),
            ]);
            if (!is_array($verification) || ($verification['verified'] ?? false) !== true
                || !is_string($verification['implementation_ref'] ?? null)
                || !hash_equals($ref, (string) $verification['implementation_ref'])) {
                throw new RuntimeException('Native implementation evidence is incomplete or drifted.');
            }
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'implemented','implementation_ref' => $ref], 'AppealImplemented', 'appeal_implementation', RequestGuard::idempotencyKey($request), [
                'implementation_ref' => $ref,
                'verification_ref' => sanitize_text_field((string) ($verification['verification_ref'] ?? '')),
            ], $this->now());
"""
assert old in s
s = s.replace(old, new, 1)

old = """            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.decision');
            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'under_review');
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'under_review','outcome' => 'remand'], 'AppealDecided', 'appeal_remand', RequestGuard::idempotencyKey($request), ['reason' => sanitize_textarea_field((string) $request->get_param('reason'))], $this->now());
"""
new = """            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.decision');
            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            if (!is_string($appeal['reviewer_ref'] ?? null) || trim((string) $appeal['reviewer_ref']) === ''
                || !hash_equals((string) $appeal['reviewer_ref'], $context->actorReference())) {
                throw new RuntimeException('Only the independently assigned reviewer may remand the appeal.');
            }
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'under_review');
            $reason = sanitize_textarea_field((string) $request->get_param('reason'));
            if ($reason === '') {
                throw new RuntimeException('A reasoned remand decision is required.');
            }
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'under_review','outcome' => 'remand'], 'AppealDecided', 'appeal_remand', RequestGuard::idempotencyKey($request), ['reason' => $reason], $this->now());
"""
assert old in s
s = s.replace(old, new, 1)
p.write_text(s)

t = Path('tests/c2n-fresh40.php')
x = t.read_text()
marker = "\nif($failures!==[]){fwrite(STDERR,implode(\"\\n\",$failures).\"\\n\");exit(1);}fwrite(STDOUT,\"CF-02 fresh 40-round regression register passed through round 20.\\n\");"
assert marker in x
add = r'''

$test(21,'appeal remand remains assigned-reviewer-only and implementation confirmation requires server-side authoritative verification',static function()use($read):void{
    $controller=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    assert(str_contains($controller, 'Only the independently assigned reviewer may remand the appeal.'));
    assert(str_contains($controller, 'A reasoned remand decision is required.'));
    assert(str_contains($controller, "cf02_verify_appeal_implementation"));
    assert(str_contains($controller, "verification['verified']"));
    assert(!str_contains($controller, "native_version_matches"));
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}fwrite(STDOUT,"CF-02 fresh 40-round regression register passed through round 21.\n");'''
x=x.replace(marker,add)
t.write_text(x)
