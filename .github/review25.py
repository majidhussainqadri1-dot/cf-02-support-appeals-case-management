from pathlib import Path
p=Path('src/Infrastructure/WordPress/OperationsRepository.php')
s=p.read_text()
s=s.replace("            'case.search.scoped', 'queue.manage', 'audit.sample.read'\n", "            'case.search.scoped', 'queue.manage'\n", 1)
s=s.replace("        if ($context->hasAnyCapability('queue.manage', 'audit.sample.read')) {\n            return $row;\n        }\n", "        if ($context->hasCapability('queue.manage')) {\n            return $row;\n        }\n", 1)
old="""        if ($allowStaff && $context->hasAnyCapability('appeal.queue.read', 'appeal.review', 'appeal.decision')) {\n            if ($context->hasCapability('appeal.review') && $row['reviewer_ref'] !== null\n                && !hash_equals((string) $row['reviewer_ref'], $context->actorReference())\n                && !$context->hasCapability('appeal.queue.read')) {\n                throw new RuntimeException('Appeal not found.');\n            }\n            return $row;\n        }\n"""
new="""        if ($allowStaff && $context->hasCapability('appeal.queue.read')) {\n            return $row;\n        }\n        if ($allowStaff && $context->hasAnyCapability('appeal.review', 'appeal.decision', 'appeal.native.request', 'appeal.implementation.confirm')) {\n            if (!is_string($row['reviewer_ref'] ?? null) || trim((string) $row['reviewer_ref']) === ''\n                || !hash_equals((string) $row['reviewer_ref'], $context->actorReference())) {\n                throw new RuntimeException('Appeal not found.');\n            }\n            return $row;\n        }\n"""
if old not in s: raise SystemExit('appeal access block not found')
s=s.replace(old,new,1)
p.write_text(s)

t=Path('tests/c2n-r24-r33.php')
s=t.read_text()
insert="""
$test(25,'object-level authorization keeps appeal review assigned and audit sampling non-global',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(!str_contains($repo, "hasAnyCapability('queue.manage', 'audit.sample.read')"));
    assert(!str_contains($repo, "'case.search.scoped', 'queue.manage', 'audit.sample.read'"));
    assert(str_contains($repo, "if ($context->hasCapability('appeal.queue.read'))"));
    assert(str_contains($repo, "hasAnyCapability('appeal.review', 'appeal.decision', 'appeal.native.request', 'appeal.implementation.confirm')"));
    assert(str_contains($repo, "!hash_equals((string) $row['reviewer_ref'], $context->actorReference())"));
});
"""
s=s.replace("\nif($failures!==[])",insert+"\nif($failures!==[])",1)
s=s.replace('passed through R24.','passed through R25.')
t.write_text(s)
