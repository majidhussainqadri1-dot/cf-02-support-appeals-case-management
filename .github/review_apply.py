from pathlib import Path
ROOT=Path('.')
def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)
p='src/Infrastructure/WordPress/OperationsRepository.php'
s=read(p)
old="""        $required = ['accuracy','accessibility','compliance','empathy','security'];
        $keys = array_keys($scores);
        sort($keys);
        if ($keys !== $required || !in_array($sampleBasis, ['random','risk','breach','reopen','complaint'], true)) {
"""
new="""        $required = ['accuracy','accessibility','compliance','empathy','security'];
        sort($required);
        $keys = array_keys($scores);
        sort($keys);
        if ($keys !== $required || !in_array($sampleBasis, ['random','risk','breach','reopen','complaint'], true)) {
"""
if old not in s: raise SystemExit('R40 quality rubric block missing')
s=s.replace(old,new,1)
write(p,s)
tp='tests/c2q-r36-r45.php'
t=read(tp); needle='if($failures!==[]){exit(1);}'
block=r'''
$test(40,'quality rubric compares normalized key sets instead of rejecting a complete valid rubric by ordering',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $posRequired=strpos($repo,'$required = [\'accuracy\',\'accessibility\',\'compliance\',\'empathy\',\'security\'];');
    $posSort=strpos($repo,'sort($required);',$posRequired);
    $posKeys=strpos($repo,'$keys = array_keys($scores);',$posRequired);
    assert($posRequired!==false && $posSort!==false && $posKeys!==false && $posRequired<$posSort && $posSort<$posKeys);
});
'''
if needle not in t: raise SystemExit('R40 test marker missing')
t=t.replace(needle,block+needle,1).replace('passed through R38','passed through R40',1)
write(tp,t)
print('R40 correction materialized')
