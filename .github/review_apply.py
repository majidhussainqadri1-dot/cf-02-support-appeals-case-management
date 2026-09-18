from pathlib import Path
ROOT=Path('.')
def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)
p='src/Infrastructure/WordPress/OperationsRepository.php'
s=read(p)
old="""    public function appealProjection(string $appealId, PrincipalContext $context): array
    {
        $appeal = $this->appealForActor($appealId, $context);
        $dossier = $this->row($this->wpdb->prepare(
            \"SELECT dossier_uuid,appeal_uuid,original_decision_ref,original_decision_hash,policy_version,evidence_refs_json,submissions_json,dossier_hash,record_version,created_at,updated_at
             FROM {$this->tables['dossiers']} WHERE appeal_uuid=%s LIMIT 1\",
            $appealId
        ));
        return ['appeal' => $appeal, 'dossier' => $dossier];
    }
"""
new="""    public function appealProjection(string $appealId, PrincipalContext $context): array
    {
        $appeal = $this->appealForActor($appealId, $context);
        $appellantAccess = hash_equals((string) $appeal['appellant_ref'], $context->actorReference())
            || $context->represents((string) $appeal['appellant_ref']);
        $assignedReviewerAccess = is_string($appeal['reviewer_ref'] ?? null)
            && hash_equals((string) $appeal['reviewer_ref'], $context->actorReference())
            && $context->hasAnyCapability('appeal.review', 'appeal.decision', 'appeal.native.request', 'appeal.implementation.confirm');
        if (!$appellantAccess && !$assignedReviewerAccess) {
            return ['appeal' => $appeal, 'dossier' => null];
        }
        $dossier = $this->row($this->wpdb->prepare(
            \"SELECT dossier_uuid,appeal_uuid,original_decision_ref,original_decision_hash,policy_version,evidence_refs_json,submissions_json,dossier_hash,record_version,created_at,updated_at
             FROM {$this->tables['dossiers']} WHERE appeal_uuid=%s LIMIT 1\",
            $appealId
        ));
        return ['appeal' => $appeal, 'dossier' => $dossier];
    }
"""
if old not in s: raise SystemExit('R38 appealProjection block missing')
s=s.replace(old,new,1)
write(p,s)
tp='tests/c2q-r36-r45.php'
t=read(tp)
needle='if($failures!==[]){exit(1);}'
block=r'''
$test(38,'appeal queue readers receive queue metadata but not the evidence dossier unless appellant or assigned reviewer',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo,'$appellantAccess = hash_equals'));
    assert(str_contains($repo,'$assignedReviewerAccess = is_string'));
    assert(str_contains($repo,"return ['appeal' => \$appeal, 'dossier' => null];"));
    assert(str_contains($repo,"hasAnyCapability('appeal.review', 'appeal.decision', 'appeal.native.request', 'appeal.implementation.confirm')"));
});
'''
if needle not in t: raise SystemExit('R38 test marker missing')
t=t.replace(needle,block+needle,1).replace('passed through R37','passed through R38',1)
write(tp,t)
print('R38 corrections materialized')
