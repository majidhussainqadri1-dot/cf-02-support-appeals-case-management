from pathlib import Path
ROOT=Path('.')
def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)
p='src/Infrastructure/WordPress/OperationsRepository.php'
s=read(p)
marker="    /** @return array<string,mixed> */\n    public function caseProjection("
helper="""    private function activeAssignmentHasScope(SupportCaseId $caseId, PrincipalContext $context, string $scope): bool
    {
        $row = $this->row($this->wpdb->prepare(
            \"SELECT scopes_json FROM {$this->tables['assignments']}
             WHERE case_uuid=%s AND agent_ref=%s AND ended_at IS NULL
             ORDER BY id DESC LIMIT 1\",
            $caseId->value(), $context->actorReference()
        ));
        if ($row === null || !is_string($row['scopes_json'] ?? null)) {
            return false;
        }
        $decoded = json_decode((string) $row['scopes_json'], true);
        if (!is_array($decoded)) {
            return false;
        }
        return in_array($scope, array_values(array_filter($decoded, 'is_string')), true);
    }

"""
idx=s.find(marker)
if idx<0: raise SystemExit('R37 caseProjection marker missing')
if 'private function activeAssignmentHasScope' not in s:
    s=s[:idx]+helper+s[idx:]
old="""        if ($staff && !$context->hasAnyCapability('case.sensitive.read', 'case.specialist.read')) {
            $visibility = \"visibility IN ('requester','internal')\";
        }
"""
new="""        if ($staff) {
            $restrictedScope = ($context->hasCapability('case.sensitive.read')
                    && $this->activeAssignmentHasScope($caseId, $context, 'case.sensitive.read'))
                || ($context->hasCapability('case.specialist.read')
                    && $this->activeAssignmentHasScope($caseId, $context, 'case.specialist.read'));
            if (!$restrictedScope) {
                $visibility = \"visibility IN ('requester','internal')\";
            }
        }
"""
if old not in s: raise SystemExit('R37 restricted visibility block missing')
s=s.replace(old,new,1)
old2="""        if ($staff && (!$context->hasCapability('case.sensitive.read')
            || !$context->recentlyAuthenticated(new DateTimeImmutable('now', new DateTimeZone('UTC'))))) {
"""
new2="""        if ($staff && (!$context->hasCapability('case.sensitive.read')
            || !$this->activeAssignmentHasScope($caseId, $context, 'case.sensitive.read')
            || !$context->recentlyAuthenticated(new DateTimeImmutable('now', new DateTimeZone('UTC'))))) {
"""
if old2 not in s: raise SystemExit('R37 attachment gate missing')
s=s.replace(old2,new2,1)
old3="""            if (!$context->hasCapability('case.sensitive.read')
                || !$context->recentlyAuthenticated(new DateTimeImmutable('now', new DateTimeZone('UTC')))) {
"""
new3="""            if (!$context->hasCapability('case.sensitive.read')
                || !$this->activeAssignmentHasScope($caseId, $context, 'case.sensitive.read')
                || !$context->recentlyAuthenticated(new DateTimeImmutable('now', new DateTimeZone('UTC')))) {
"""
if old3 not in s: raise SystemExit('R37 link gate missing')
s=s.replace(old3,new3,1)
write(p,s)

tp='tests/c2q-r36-r45.php'
t=read(tp)
needle='if($failures!==[]){exit(1);}'
block=r'''
$test(37,'sensitive case projections require the active assignment to carry the same JIT scope',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo,'private function activeAssignmentHasScope('));
    assert(substr_count($repo,"activeAssignmentHasScope(\$caseId, \$context, 'case.sensitive.read')")>=2);
    assert(str_contains($repo,"activeAssignmentHasScope(\$caseId, \$context, 'case.specialist.read')"));
    assert(str_contains($repo,"WHERE case_uuid=%s AND agent_ref=%s AND ended_at IS NULL"));
});
'''
if needle not in t: raise SystemExit('R37 test marker missing')
t=t.replace(needle,block+needle,1).replace('passed through R36','passed through R37',1)
write(tp,t)
print('R37 corrections materialized')
