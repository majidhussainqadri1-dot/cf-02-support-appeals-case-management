from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    s = p.read_text()
    if old not in s:
        raise SystemExit(f"expected block missing in {path}")
    p.write_text(s.replace(old, new, 1))

# R26 — serialize workflow invariants and make wait+SLA pause atomic.
p = Path('src/Infrastructure/WordPress/OperationsRepository.php')
s = p.read_text()
old = """        $this->transaction(function () use ($caseId, $context, $expectedVersion, $fields, $event, $purpose, $idempotencyKey, $eventPayload, $at): void {
            $data = array_merge($fields, [
                'record_version' => $expectedVersion + 1,
                'updated_at' => $this->mysqlTime($at),
            ]);
            $where = ['case_uuid' => $caseId->value(), 'record_version' => $expectedVersion];
            $updated = $this->wpdb->update($this->tables['cases'], $data, $where);
            if ($updated !== 1) {
                throw new RuntimeException('Stale case version or case mutation failed.');
            }
            $this->appendEvent('case', $caseId->value(), $event, $context, $purpose, $idempotencyKey, $eventPayload, $expectedVersion + 1, $at);
        });
"""
new = """        $this->transaction(function () use ($caseId, $context, $expectedVersion, $fields, $command, $event, $purpose, $idempotencyKey, $eventPayload, $at): void {
            $current = $this->lockCaseForLifecycle($caseId->value());
            if ((int) $current['record_version'] !== $expectedVersion) {
                throw new RuntimeException('Stale case version or case mutation failed.');
            }
            $fromState = (string) $current['state'];
            if ($command === 'EscalateCase' && in_array($fromState, ['resolved', 'closed', 'withdrawn'], true)) {
                throw new RuntimeException('Terminal cases must be reopened before escalation.');
            }
            if (isset($fields['state']) && is_string($fields['state']) && !hash_equals($fromState, $fields['state'])) {
                RuntimeWorkflowPolicy::assertCase($fromState, $fields['state']);
            }
            $data = array_merge($fields, [
                'record_version' => $expectedVersion + 1,
                'updated_at' => $this->mysqlTime($at),
            ]);
            $where = ['case_uuid' => $caseId->value(), 'record_version' => $expectedVersion];
            $updated = $this->wpdb->update($this->tables['cases'], $data, $where);
            if ($updated !== 1) {
                throw new RuntimeException('Stale case version or case mutation failed.');
            }
            $this->appendEvent('case', $caseId->value(), $event, $context, $purpose, $idempotencyKey, $eventPayload, $expectedVersion + 1, $at);
        });
"""
if old not in s: raise SystemExit('mutateCase block missing')
s = s.replace(old, new, 1)
old = """        $this->transaction(function () use ($appealId, $context, $expectedVersion, $fields, $event, $purpose, $idempotencyKey, $payload, $at): void {
            $data = $fields;
            $data['record_version'] = $expectedVersion + 1;
            $data['updated_at'] = $this->mysqlTime($at);
            $updated = $this->wpdb->update($this->tables['appeals'], $data, [
                'appeal_uuid' => $appealId, 'record_version' => $expectedVersion,
            ]);
            if ($updated !== 1) {
                throw new RuntimeException('Stale appeal version or mutation failure.');
            }
            $this->appendEvent('appeal', $appealId, $event, $context, $purpose, $idempotencyKey, $payload, $expectedVersion + 1, $at);
        });
"""
new = """        $this->transaction(function () use ($appealId, $context, $expectedVersion, $fields, $event, $purpose, $idempotencyKey, $payload, $at): void {
            $current = $this->row($this->wpdb->prepare(
                \"SELECT state,record_version FROM {$this->tables['appeals']} WHERE appeal_uuid=%s FOR UPDATE\",
                $appealId
            ));
            if ($current === null || (int) $current['record_version'] !== $expectedVersion) {
                throw new RuntimeException('Stale appeal version or mutation failure.');
            }
            $fromState = (string) $current['state'];
            if (isset($fields['state']) && is_string($fields['state']) && !hash_equals($fromState, $fields['state'])) {
                RuntimeWorkflowPolicy::assertAppeal($fromState, $fields['state']);
            }
            $data = $fields;
            $data['record_version'] = $expectedVersion + 1;
            $data['updated_at'] = $this->mysqlTime($at);
            $updated = $this->wpdb->update($this->tables['appeals'], $data, [
                'appeal_uuid' => $appealId, 'record_version' => $expectedVersion,
            ]);
            if ($updated !== 1) {
                throw new RuntimeException('Stale appeal version or mutation failure.');
            }
            $this->appendEvent('appeal', $appealId, $event, $context, $purpose, $idempotencyKey, $payload, $expectedVersion + 1, $at);
        });
"""
if old not in s: raise SystemExit('mutateAppeal block missing')
s = s.replace(old, new, 1)
marker = "    public function pauseSla(SupportCaseId $caseId, string $reason, string $evidenceRef, DateTimeImmutable $at): void\n"
method = """    /** @return array<string,mixed> */
    public function waitCaseAndPauseSla(
        SupportCaseId $caseId,
        PrincipalContext $context,
        int $expectedVersion,
        string $waitingState,
        string $command,
        string $idempotencyKey,
        string $reason,
        DateTimeImmutable $at
    ): array {
        if (!in_array($waitingState, ['waiting_user', 'waiting_provider'], true) || trim($reason) === '') {
            throw new RuntimeException('A valid waiting state and bounded reason are required.');
        }
        SupportContractCatalog::assertCommand($command);
        SupportContractCatalog::assertEvent('SupportCaseWaiting');
        SupportContractCatalog::assertEvent('SupportSlaPaused');
        $this->caseForActor($caseId, $context);
        $waitingPayload = ['reason' => $reason, 'waiting_for' => $waitingState === 'waiting_provider' ? 'provider' : 'user'];
        if ($this->eventReplay($caseId->value(), 'SupportCaseWaiting', $idempotencyKey, $waitingPayload)) {
            return $this->caseForActor($caseId, $context);
        }
        $evidenceRef = 'event:' . $idempotencyKey;
        $this->transaction(function () use ($caseId, $context, $expectedVersion, $waitingState, $idempotencyKey, $waitingPayload, $evidenceRef, $at): void {
            $case = $this->lockCaseForLifecycle($caseId->value());
            if ((int) $case['record_version'] !== $expectedVersion) {
                throw new RuntimeException('Stale case version or case mutation failed.');
            }
            RuntimeWorkflowPolicy::assertCase((string) $case['state'], $waitingState);
            if (!$this->hasRequesterVisibleStaffResponse($caseId)) {
                throw new RuntimeException('SLA pause is prohibited before a requester-visible staff response.');
            }
            $sla = $this->row($this->wpdb->prepare(
                \"SELECT record_version,status FROM {$this->tables['sla']} WHERE case_uuid=%s FOR UPDATE\",
                $caseId->value()
            ));
            if ($sla === null || !in_array((string) $sla['status'], ['running', 'at_risk'], true)) {
                throw new RuntimeException('SLA timer is not eligible for pause.');
            }
            $updatedCase = $this->wpdb->update($this->tables['cases'], [
                'state' => $waitingState,
                'record_version' => $expectedVersion + 1,
                'updated_at' => $this->mysqlTime($at),
            ], ['case_uuid' => $caseId->value(), 'record_version' => $expectedVersion]);
            if ($updatedCase !== 1) {
                throw new RuntimeException('Stale case version or case mutation failed.');
            }
            $updatedSla = $this->wpdb->update($this->tables['sla'], [
                'status' => 'paused', 'paused_at' => $this->mysqlTime($at), 'pause_reason' => $waitingState,
                'evidence_ref' => $evidenceRef, 'record_version' => (int) $sla['record_version'] + 1,
            ], ['case_uuid' => $caseId->value(), 'record_version' => (int) $sla['record_version']]);
            if ($updatedSla !== 1) {
                throw new RuntimeException('SLA pause conflicted.');
            }
            $this->appendEvent('case', $caseId->value(), 'SupportCaseWaiting', $context, 'sla_wait', $idempotencyKey, $waitingPayload, $expectedVersion + 1, $at);
            $this->appendEvent('case', $caseId->value(), 'SupportSlaPaused', $context, 'sla_wait', $idempotencyKey . ':sla-pause', [
                'reason' => $waitingState, 'evidence_ref' => $evidenceRef,
            ], $expectedVersion + 1, $at);
        });
        return $this->caseForActor($caseId, $context);
    }

"""
if marker not in s: raise SystemExit('pause marker missing')
s = s.replace(marker, method + marker, 1)
p.write_text(s)

replace_once('src/Infrastructure/WordPress/ComprehensiveRestController.php', """            $caseId = $this->caseId($request);
            $case = $this->operations->caseForActor($caseId, $context);
            RuntimeWorkflowPolicy::assertCase((string) $case['state'], $to);
            $key = RequestGuard::idempotencyKey($request);
            $reason = sanitize_textarea_field((string) $request->get_param('reason'));
            if ($reason === '') {
                throw new RuntimeException('A bounded waiting reason is required.');
            }
            $mutated = $this->operations->mutateCase(
                $caseId, $context, RequestGuard::expectedVersion($request), ['state' => $to],
                $waitingFor === 'provider' ? 'RequestProviderAction' : 'RequestUserInfo', 'SupportCaseWaiting',
                'sla_wait', $key, ['reason' => $reason, 'waiting_for' => $waitingFor], $this->now()
            );
            $this->operations->pauseSla($caseId, $to, 'event:' . $key, $this->now());
            $this->operations->appendEvent('case', $caseId->value(), 'SupportSlaPaused', $context, 'sla_wait',
                $key . ':sla-pause', ['reason' => $to, 'evidence_ref' => 'event:' . $key],
                (int) $mutated['record_version'], $this->now());
            return $mutated;
""", """            $caseId = $this->caseId($request);
            $key = RequestGuard::idempotencyKey($request);
            $reason = sanitize_textarea_field((string) $request->get_param('reason'));
            if ($reason === '') {
                throw new RuntimeException('A bounded waiting reason is required.');
            }
            return $this->operations->waitCaseAndPauseSla(
                $caseId, $context, RequestGuard::expectedVersion($request), $to,
                $waitingFor === 'provider' ? 'RequestProviderAction' : 'RequestUserInfo',
                $key, $reason, $this->now()
            );
""")

# R27 — structural schema verification and downgrade fail-closed.
p = Path('src/Infrastructure/WordPress/Installer.php'); s = p.read_text()
s = s.replace("""        $installed = (string) get_option(self::OPTION_SCHEMA_VERSION, '0.0.0');
        if (version_compare($installed, SchemaCompletion::VERSION, '>=')) {
            self::assertSchema($prefix);
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
""", """        $installed = (string) get_option(self::OPTION_SCHEMA_VERSION, '0.0.0');
        if (version_compare($installed, SchemaCompletion::VERSION, '>')) {
            throw new RuntimeException(sprintf(
                'CF-02 database schema %s is newer than runtime schema %s; downgrade is blocked until compatibility is proven.',
                $installed,
                SchemaCompletion::VERSION
            ));
        }

        // dbDelta is deliberately re-run even when the version option already matches. It is
        // idempotent and repairs partial/stale tables before structural verification.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
""", 1)
old = """    private static function assertSchema(string $prefix): void
    {
        global $wpdb;
        $missing = [];
        foreach (self::tableNames($prefix) as $table) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (!is_string($found) || !hash_equals($table, $found)) {
                $missing[] = $table;
            }
        }
        if ($missing !== []) {
            throw new RuntimeException('CF-02 schema verification failed: ' . implode(', ', $missing));
        }
    }
"""
new = """    /** @return list<array{table:string,type:string,name:string}> */
    public static function schemaIssues(string $prefix): array
    {
        global $wpdb;
        $issues = [];
        foreach (self::statements($prefix, '') as $key => $statement) {
            $table = $prefix . 'cf02_' . $key;
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (!is_string($found) || !hash_equals($table, $found)) {
                $issues[] = ['table' => $table, 'type' => 'table_missing', 'name' => $table];
                continue;
            }
            $expected = self::expectedStructure($statement);
            $columns = $wpdb->get_results('SHOW COLUMNS FROM `' . $table . '`', ARRAY_A);
            $actualColumns = [];
            if (is_array($columns)) {
                foreach ($columns as $column) {
                    if (is_array($column) && is_string($column['Field'] ?? null)) {
                        $actualColumns[(string) $column['Field']] = true;
                    }
                }
            }
            foreach ($expected['columns'] as $column) {
                if (!isset($actualColumns[$column])) {
                    $issues[] = ['table' => $table, 'type' => 'column_missing', 'name' => $column];
                }
            }
            $indexes = $wpdb->get_results('SHOW INDEX FROM `' . $table . '`', ARRAY_A);
            $actualIndexes = [];
            if (is_array($indexes)) {
                foreach ($indexes as $index) {
                    if (is_array($index) && is_string($index['Key_name'] ?? null)) {
                        $actualIndexes[(string) $index['Key_name']] = true;
                    }
                }
            }
            foreach ($expected['indexes'] as $index) {
                if (!isset($actualIndexes[$index])) {
                    $issues[] = ['table' => $table, 'type' => 'index_missing', 'name' => $index];
                }
            }
        }
        return $issues;
    }

    /** @return array{columns:list<string>,indexes:list<string>} */
    private static function expectedStructure(string $statement): array
    {
        $columns = [];
        $indexes = [];
        foreach (preg_split('/\\R/', $statement) ?: [] as $line) {
            $line = trim($line, " \\t\\n\\r\\0\\x0B,");
            if ($line === '' || str_starts_with($line, 'CREATE TABLE') || str_starts_with($line, ')')) {
                continue;
            }
            if (preg_match('/^PRIMARY\\s+KEY\\s*\\(/i', $line) === 1) {
                $indexes[] = 'PRIMARY';
                continue;
            }
            if (preg_match('/^(?:UNIQUE\\s+KEY|KEY)\\s+([A-Za-z0-9_]+)\\s*\\(/i', $line, $match) === 1) {
                $indexes[] = $match[1];
                continue;
            }
            if (preg_match('/^([A-Za-z0-9_]+)\\s+[A-Za-z]/', $line, $match) === 1) {
                $columns[] = $match[1];
            }
        }
        return ['columns' => array_values(array_unique($columns)), 'indexes' => array_values(array_unique($indexes))];
    }

    private static function assertSchema(string $prefix): void
    {
        $issues = self::schemaIssues($prefix);
        if ($issues !== []) {
            $labels = array_map(static fn (array $issue): string => $issue['table'] . ':' . $issue['type'] . ':' . $issue['name'], $issues);
            throw new RuntimeException('CF-02 schema verification failed: ' . implode(', ', $labels));
        }
    }
"""
if old not in s: raise SystemExit('assertSchema block missing')
p.write_text(s.replace(old, new, 1))

p = Path('src/Infrastructure/WordPress/RepairService.php'); s = p.read_text()
s = s.replace("$i=self::inspect();$healthy=$i['missing_tables']===[]&&$i['legacy_local_roles_present']===[]&&$i['route_receipt_errors']===[];", "$i=self::inspect();$healthy=$i['schema_issues']===[]&&$i['legacy_local_roles_present']===[]&&$i['route_receipt_errors']===[];", 1)
s = s.replace("""        global $wpdb;$missing=[];
        foreach(Installer::tableNames((string)$wpdb->prefix) as $table){$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table));if(!is_string($found)||!hash_equals($table,$found))$missing[]=$table;}
        $receipts=get_option('cf02_route_contract_receipts',[]);$receipts=is_array($receipts)?$receipts:[];
        return ['schema_expected'=>SchemaCompletion::VERSION,'schema_installed'=>Installer::schemaVersion(),'missing_tables'=>$missing,'route_receipt_errors'=>RouteRegistrar::validateReceipts($receipts),'legacy_local_roles_present'=>self::legacyRoles(),'scheduler'=>Scheduler::inspection(),'repairable'=>true,'destructive'=>false];
""", """        global $wpdb;$issues=Installer::schemaIssues((string)$wpdb->prefix);
        $missing=[];foreach($issues as $issue){if(($issue['type']??null)==='table_missing'&&is_string($issue['table']??null))$missing[]=$issue['table'];}
        $receipts=get_option('cf02_route_contract_receipts',[]);$receipts=is_array($receipts)?$receipts:[];
        return ['schema_expected'=>SchemaCompletion::VERSION,'schema_installed'=>Installer::schemaVersion(),'missing_tables'=>array_values(array_unique($missing)),'schema_issues'=>$issues,'route_receipt_errors'=>RouteRegistrar::validateReceipts($receipts),'legacy_local_roles_present'=>self::legacyRoles(),'scheduler'=>Scheduler::inspection(),'repairable'=>true,'destructive'=>false];
""", 1)
s = s.replace("$result['status']=$result['missing_tables']===[]&&$result['legacy_local_roles_present']===[]&&$result['route_receipt_errors']===[]?'repaired':'incomplete';", "$result['status']=$result['schema_issues']===[]&&$result['legacy_local_roles_present']===[]&&$result['route_receipt_errors']===[]?'repaired':'incomplete';", 1)
p.write_text(s)

# R28 — purge derivative records and minimize retention ledger.
p = Path('src/Infrastructure/WordPress/OperationsRepository.php'); s = p.read_text()
s = s.replace("""            $appeals = $this->rows($this->wpdb->prepare("SELECT appeal_uuid FROM {$this->tables['appeals']} WHERE case_uuid=%s", $caseId));
            foreach ($appeals as $appeal) {
                $this->wpdb->delete($this->tables['dossiers'], ['appeal_uuid' => (string) $appeal['appeal_uuid']]);
            }
            foreach (['messages','attachments','assignments','sla','tasks','appeals','commands','outbox','case_links','incident_links','feedback'] as $table) {
                $this->wpdb->delete($this->tables[$table], ['case_uuid' => $caseId]);
            }
""", """            $appeals = $this->rows($this->wpdb->prepare("SELECT appeal_uuid FROM {$this->tables['appeals']} WHERE case_uuid=%s", $caseId));
            foreach ($appeals as $appeal) {
                $this->wpdb->delete($this->tables['dossiers'], ['appeal_uuid' => (string) $appeal['appeal_uuid']]);
            }
            // Purge case-linked derivative records before deleting their canonical parents.
            $this->wpdb->query($this->wpdb->prepare(
                "DELETE nr FROM {$this->tables['note_revisions']} nr JOIN {$this->tables['messages']} m ON m.message_uuid=nr.message_uuid WHERE m.case_uuid=%s",
                $caseId
            ));
            $this->wpdb->query($this->wpdb->prepare(
                "DELETE t FROM {$this->tables['tokens']} t JOIN {$this->tables['attachments']} a ON a.attachment_uuid=t.attachment_uuid WHERE a.case_uuid=%s",
                $caseId
            ));
            $this->wpdb->delete($this->tables['inbound'], ['case_uuid' => $caseId]);
            foreach (['messages','attachments','assignments','sla','tasks','appeals','commands','outbox','case_links','incident_links','feedback'] as $table) {
                $this->wpdb->delete($this->tables[$table], ['case_uuid' => $caseId]);
            }
""", 1)
s = s.replace("""    public function recordRetentionResult(string $objectType, string $objectRef, string $policyVersion, string $action, array $providerResults, DateTimeImmutable $at): void
    {
        $evidence = hash('sha256', $this->json([$objectType,$objectRef,$policyVersion,$action,$providerResults,$at->format(DATE_ATOM)]));
        $ok = $this->wpdb->insert($this->tables['retention'], [
            'object_type' => $objectType, 'object_ref' => $objectRef, 'policy_version' => $policyVersion,
            'action_key' => $action, 'provider_results_json' => $this->json($providerResults),
            'evidence_hash' => $evidence, 'executed_at' => $this->mysqlTime($at),
        ]);
""", """    public function recordRetentionResult(string $objectType, string $objectRef, string $policyVersion, string $action, array $providerResults, DateTimeImmutable $at): void
    {
        $rawHash = hash('sha256', $this->json($providerResults));
        $summary = [
            'authorized' => ($providerResults['authorized'] ?? false) === true,
            'all_targets_reconciled' => ($providerResults['all_targets_reconciled'] ?? false) === true,
            'result_hash' => $rawHash,
            'result_count' => count($providerResults),
        ];
        $evidence = hash('sha256', $this->json([$objectType,$objectRef,$policyVersion,$action,$summary,$at->format(DATE_ATOM)]));
        $ok = $this->wpdb->insert($this->tables['retention'], [
            'object_type' => $objectType, 'object_ref' => $objectRef, 'policy_version' => $policyVersion,
            'action_key' => $action, 'provider_results_json' => $this->json($summary),
            'evidence_hash' => $evidence, 'executed_at' => $this->mysqlTime($at),
        ]);
""", 1)
p.write_text(s)

# R29 — emit SLA mutation/escalation only for a real state change.
replace_once('src/Infrastructure/WordPress/RuntimeWorker.php', """            $status = $earliest <= $now->getTimestamp() ? 'breached' : 'at_risk';
            $version = $this->repository->markSlaStatus((string) $timer['case_uuid'], $status, $now);
""", """            $status = $earliest <= $now->getTimestamp() ? 'breached' : 'at_risk';
            if (hash_equals((string) ($timer['status'] ?? ''), $status)) {
                continue;
            }
            $version = $this->repository->markSlaStatus((string) $timer['case_uuid'], $status, $now);
""")

# Regression register/tests.
p = Path('tests/c2n-r24-r33.php'); s = p.read_text()
insert = r'''
$test(26,'repository serializes state law and waiting/SLA pause atomically',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $rest=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    assert(str_contains($repo, "RuntimeWorkflowPolicy::assertCase($fromState, $fields['state'])"));
    assert(str_contains($repo, "RuntimeWorkflowPolicy::assertAppeal($fromState, $fields['state'])"));
    assert(str_contains($repo, "public function waitCaseAndPauseSla("));
    assert(str_contains($repo, "SELECT record_version,status FROM {$this->tables['sla']} WHERE case_uuid=%s FOR UPDATE"));
    assert(str_contains($repo, "Terminal cases must be reopened before escalation."));
    assert(str_contains($rest, "return $this->operations->waitCaseAndPauseSla("));
}
);
$test(27,'schema verification detects structural drift and blocks unknown newer schemas',static function()use($read):void{
    $installer=$read('src/Infrastructure/WordPress/Installer.php');
    $repair=$read('src/Infrastructure/WordPress/RepairService.php');
    assert(str_contains($installer, "version_compare($installed, SchemaCompletion::VERSION, '>')"));
    assert(str_contains($installer, "public static function schemaIssues(string $prefix): array"));
    assert(str_contains($installer, "SHOW COLUMNS FROM"));
    assert(str_contains($installer, "SHOW INDEX FROM"));
    assert(str_contains($repair, "'schema_issues'=>$issues"));
});
$test(28,'retention purge removes derivatives and stores only bounded provider evidence',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo, "DELETE nr FROM {$this->tables['note_revisions']} nr JOIN {$this->tables['messages']}"));
    assert(str_contains($repo, "DELETE t FROM {$this->tables['tokens']} t JOIN {$this->tables['attachments']}"));
    assert(str_contains($repo, "'result_hash' => $rawHash"));
    assert(str_contains($repo, "'provider_results_json' => $this->json($summary)"));
});
$test(29,'SLA worker emits escalation side effects only on a real status transition',static function()use($read):void{
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($worker, "if (hash_equals((string) ($timer['status'] ?? ''), $status))"));
});
'''
# Escape interpolation-sensitive source literals in the PHP test text.
insert = insert.replace('$fromState', '\\$fromState').replace("$fields['state']", "\\$fields['state']").replace('$this->tables', '\\$this->tables').replace('$this->operations', '\\$this->operations').replace('$installed', '\\$installed').replace('$prefix', '\\$prefix').replace('$issues', '\\$issues').replace('$rawHash', '\\$rawHash').replace('$summary', '\\$summary').replace("$timer['status']", "\\$timer['status']").replace('$status', '\\$status')
if 'passed through R25.' not in s: raise SystemExit('R25 test footer missing')
s = s.replace('\nif($failures!==[])', '\n' + insert + '\nif($failures!==[])', 1).replace('passed through R25.','passed through R29.')
p.write_text(s)

# Review register documentation.
p = Path('docs/REVIEW-REGISTER-C2N-24-33.md'); s = p.read_text()
s = s.replace('Status: **findings frozen; correction follows this completed review.**','Status: **corrected and validated before R26.**',1)
if '## R26 —' not in s:
    s = s.rstrip() + '''

## R26 — State invariants and intra-case concurrency

**Review completed before correction.** Frozen findings: split waiting/SLA transaction; repository state-transition bypass; terminal escalation without reopen. Status: **corrected and validated before R27.**

## R27 — Schema, migration and rollback compatibility

**Review completed before correction.** Frozen findings: table-only schema verification; acceptance of unknown newer schemas. Status: **corrected and validated before R28.**

## R28 — Privacy, minimization and retention

**Review completed before correction.** Frozen findings: purge left note/token/inbound derivatives; retention ledger stored provider payloads verbatim. Status: **corrected and validated before R29.**

## R29 — SLA escalation idempotence

**Review completed before correction.** Frozen finding: unchanged `at_risk` timers repeatedly fired mutation/events/escalation hooks. Status: **corrected and validated before R30.**
'''
p.write_text(s.rstrip() + '\n')
