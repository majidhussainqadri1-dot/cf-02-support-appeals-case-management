from pathlib import Path

repo = Path('src/Infrastructure/WordPress/OperationsRepository.php')
s = repo.read_text()

old = """        if (!in_array($reason, ['waiting_user','waiting_provider','legal_hold'], true) || trim($evidenceRef) === '') {
            throw new RuntimeException('SLA pause reason or evidence is invalid.');
        }
        $row = $this->row($this->wpdb->prepare(\"SELECT record_version,status FROM {$this->tables['sla']} WHERE case_uuid=%s LIMIT 1\", $caseId->value()));
"""
new = """        if (!in_array($reason, ['waiting_user','waiting_provider','legal_hold'], true) || trim($evidenceRef) === '') {
            throw new RuntimeException('SLA pause reason or evidence is invalid.');
        }
        if (!$this->hasRequesterVisibleStaffResponse($caseId)) {
            throw new RuntimeException('SLA pause is prohibited before a requester-visible staff response.');
        }
        $row = $this->row($this->wpdb->prepare(\"SELECT record_version,status FROM {$this->tables['sla']} WHERE case_uuid=%s LIMIT 1\", $caseId->value()));
"""
assert old in s
s = s.replace(old, new, 1)

old = """        $updated = $this->wpdb->update($this->tables['sla'], [
            'status' => 'running', 'first_response_deadline' => $shift((string) $row['first_response_deadline']),
            'update_deadline' => $shift((string) $row['update_deadline']),
            'resolution_deadline' => $shift((string) $row['resolution_deadline']),
"""
new = """        $updated = $this->wpdb->update($this->tables['sla'], [
            'status' => 'running',
            // A pause can begin only after first response, so that already-satisfied deadline must never move.
            'update_deadline' => $shift((string) $row['update_deadline']),
            'resolution_deadline' => $shift((string) $row['resolution_deadline']),
"""
assert old in s
s = s.replace(old, new, 1)

old = """    /** @return list<array<string,mixed>> */
    public function dueSla(int $limit): array
    {
        return $this->rows($this->wpdb->prepare(
            \"SELECT s.*,c.state,c.priority,c.owner_ref,c.requester_ref FROM {$this->tables['sla']} s
             JOIN {$this->tables['cases']} c ON c.case_uuid=s.case_uuid
             WHERE s.status IN ('running','at_risk') AND c.state NOT IN ('resolved','closed','withdrawn')
             AND (s.first_response_deadline<=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)
                  OR s.update_deadline<=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)
                  OR s.resolution_deadline<=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE))
             ORDER BY LEAST(s.first_response_deadline,s.update_deadline,s.resolution_deadline) ASC LIMIT %d\",
            max(1, min(250, $limit))
        ));
    }
"""
new = """    /** @return list<array<string,mixed>> */
    public function dueSla(int $limit): array
    {
        $firstResponseRecorded = \"EXISTS (
            SELECT 1 FROM {$this->tables['messages']} mfr
            WHERE mfr.case_uuid=s.case_uuid AND mfr.visibility='requester'
              AND mfr.author_ref<>c.requester_ref
              AND NOT EXISTS (
                  SELECT 1 FROM {$this->tables['representatives']} rfr
                  WHERE rfr.requester_ref=c.requester_ref AND rfr.representative_ref=mfr.author_ref
                    AND rfr.verified_at<=mfr.created_at AND rfr.expires_at>mfr.created_at
                    AND (rfr.revoked_at IS NULL OR rfr.revoked_at>mfr.created_at)
              )
        )\";
        return $this->rows($this->wpdb->prepare(
            \"SELECT s.*,c.state,c.priority,c.owner_ref,c.requester_ref,
                    CASE WHEN {$firstResponseRecorded} THEN 1 ELSE 0 END AS first_response_recorded
             FROM {$this->tables['sla']} s
             JOIN {$this->tables['cases']} c ON c.case_uuid=s.case_uuid
             WHERE s.status IN ('running','at_risk') AND c.state NOT IN ('resolved','closed','withdrawn')
             AND ((NOT {$firstResponseRecorded} AND s.first_response_deadline<=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE))
                  OR s.update_deadline<=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)
                  OR s.resolution_deadline<=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE))
             ORDER BY LEAST(
                 CASE WHEN {$firstResponseRecorded} THEN '9999-12-31 23:59:59.999999' ELSE s.first_response_deadline END,
                 s.update_deadline,s.resolution_deadline
             ) ASC LIMIT %d\",
            max(1, min(250, $limit))
        ));
    }

    private function hasRequesterVisibleStaffResponse(SupportCaseId $caseId): bool
    {
        $count = $this->value($this->wpdb->prepare(
            \"SELECT COUNT(*) FROM {$this->tables['messages']} m
             JOIN {$this->tables['cases']} c ON c.case_uuid=m.case_uuid
             WHERE m.case_uuid=%s AND m.visibility='requester' AND m.author_ref<>c.requester_ref
               AND NOT EXISTS (
                   SELECT 1 FROM {$this->tables['representatives']} r
                   WHERE r.requester_ref=c.requester_ref AND r.representative_ref=m.author_ref
                     AND r.verified_at<=m.created_at AND r.expires_at>m.created_at
                     AND (r.revoked_at IS NULL OR r.revoked_at>m.created_at)
               )\",
            $caseId->value()
        ));
        return (int) $count > 0;
    }
"""
assert old in s
s = s.replace(old, new, 1)
repo.write_text(s)

worker = Path('src/Infrastructure/WordPress/RuntimeWorker.php')
w = worker.read_text()
old = """            $deadlines = [
                new DateTimeImmutable((string) $timer['first_response_deadline'], new DateTimeZone('UTC')),
                new DateTimeImmutable((string) $timer['update_deadline'], new DateTimeZone('UTC')),
                new DateTimeImmutable((string) $timer['resolution_deadline'], new DateTimeZone('UTC')),
            ];
"""
new = """            $deadlines = [];
            if ((int) ($timer['first_response_recorded'] ?? 0) !== 1) {
                $deadlines[] = new DateTimeImmutable((string) $timer['first_response_deadline'], new DateTimeZone('UTC'));
            }
            $deadlines[] = new DateTimeImmutable((string) $timer['update_deadline'], new DateTimeZone('UTC'));
            $deadlines[] = new DateTimeImmutable((string) $timer['resolution_deadline'], new DateTimeZone('UTC'));
"""
assert old in w
w = w.replace(old, new, 1)
worker.write_text(w)

test = Path('tests/c2n-fresh40.php')
t = test.read_text()
marker = "\nif($failures!==[]){fwrite(STDERR,implode(\"\\n\",$failures).\"\\n\");exit(1);}fwrite(STDOUT,\"CF-02 fresh 40-round regression register passed through round 17.\\n\");"
assert marker in t
add = r'''

$test(20,'runtime SLA cannot pause before first response or keep treating a satisfied first-response deadline as due',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($repo, 'SLA pause is prohibited before a requester-visible staff response.'));
    assert(str_contains($repo, 'first_response_recorded'));
    assert(str_contains($repo, "NOT EXISTS ("));
    assert(!str_contains($repo, "'first_response_deadline' => $shift"));
    assert(str_contains($worker, "first_response_recorded"));
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}fwrite(STDOUT,"CF-02 fresh 40-round regression register passed through round 20.\n");'''
t = t.replace(marker, add)
test.write_text(t)
