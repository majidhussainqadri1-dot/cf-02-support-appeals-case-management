from pathlib import Path
ROOT=Path('.')
def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)
p='src/Infrastructure/WordPress/OperationsRepository.php'
s=read(p)
start=s.index('    public function purgeCase(string $caseId, array $providerResults, DateTimeImmutable $at): void\n')
end=s.index('    /** @return list<array<string,mixed>> */\n    public function dueRetention',start)
replacement=r'''    public function purgeCase(string $caseId, array $providerResults, DateTimeImmutable $at): void
    {
        if (($providerResults['all_targets_reconciled'] ?? false) !== true) {
            throw new RuntimeException('Provider/cache/search deletion reconciliation is incomplete.');
        }
        $system = $this->systemContext($at);
        $providerHash = hash('sha256', $this->json($providerResults));
        $eventKey = 'retention-purge-' . substr(hash('sha256', $caseId . "\0" . $this->json($providerResults)), 0, 40);
        $this->transaction(function () use ($caseId, $system, $providerHash, $eventKey, $at): void {
            $this->lockCaseForLifecycle($caseId, ['closed']);
            $activeHolds = (int) $this->value($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['holds']} WHERE case_uuid=%s AND state='active'", $caseId
            ));
            if ($activeHolds > 0) {
                throw new RuntimeException('Active legal or appeal hold blocks purge.');
            }
            $unresolvedAppeals = (int) $this->value($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['appeals']} WHERE case_uuid=%s AND state<>'closed'", $caseId
            ));
            if ($unresolvedAppeals > 0) {
                throw new RuntimeException('Open or unresolved appeal blocks purge until appeal closure.');
            }
            $appeals = $this->rows($this->wpdb->prepare("SELECT appeal_uuid FROM {$this->tables['appeals']} WHERE case_uuid=%s", $caseId));
            foreach ($appeals as $appeal) {
                $this->wpdb->delete($this->tables['dossiers'], ['appeal_uuid' => (string) $appeal['appeal_uuid']]);
            }
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
            $this->wpdb->query("DELETE p FROM {$this->tables['command_payloads']} p LEFT JOIN {$this->tables['commands']} c ON c.command_uuid=p.command_uuid WHERE c.command_uuid IS NULL");
            $this->wpdb->query("DELETE p FROM {$this->tables['outbox_payloads']} p LEFT JOIN {$this->tables['outbox']} o ON o.message_uuid=p.message_uuid WHERE o.message_uuid IS NULL");
            $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->tables['merge_redirects']} WHERE source_case_uuid=%s OR target_case_uuid=%s", $caseId, $caseId));
            $this->appendEvent(
                'case', $caseId, 'SupportRetentionPurgeCompleted', $system, 'retention_purge',
                $eventKey, ['provider_results_hash' => $providerHash], 0, $at
            );
            $deleted = $this->wpdb->delete($this->tables['cases'], ['case_uuid' => $caseId]);
            if ($deleted !== 1) {
                throw new RuntimeException('Canonical case purge failed.');
            }
        });
    }

'''
s=s[:start]+replacement+s[end:]
write(p,s)
tp='tests/c2q-r36-r45.php'
t=read(tp); needle='if($failures!==[]){exit(1);}'
block=r'''
$test(44,'retention purge completion evidence is written inside the same transaction before canonical deletion',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $start=strpos($repo,'public function purgeCase(');
    $end=strpos($repo,'public function dueRetention(',$start);
    $block=substr($repo,$start,$end-$start);
    $tx=strpos($block,'$this->transaction(');
    $event=strpos($block,"'SupportRetentionPurgeCompleted'");
    $delete=strpos($block,"$this->wpdb->delete($this->tables['cases']");
    assert($tx!==false && $event!==false && $delete!==false && $tx<$event && $event<$delete);
    assert(!str_contains(substr($block,$delete),'$this->appendEvent('));
});
'''
if needle not in t: raise SystemExit('R44 test marker missing')
t=t.replace(needle,block+needle,1).replace('passed through R43','passed through R44',1)
write(tp,t)
print('R44 correction materialized')
