from pathlib import Path
ROOT=Path('.')
def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)
p='src/Infrastructure/WordPress/OperationsRepository.php'
s=read(p)
old="""        if ($existing !== null) {
            return $existing;
        }
        $ok = $this->wpdb->insert($this->tables['feedback'], [
"""
new="""        if ($existing !== null) {
            return array_intersect_key($existing, array_flip(['case_uuid','rating','opted_out','submitted_at']));
        }
        $ok = $this->wpdb->insert($this->tables['feedback'], [
"""
if old not in s: raise SystemExit('R41 feedback replay block missing')
s=s.replace(old,new,1)
write(p,s)
tp='tests/c2q-r36-r45.php'
t=read(tp); needle='if($failures!==[]){exit(1);}'
block=r'''
$test(41,'feedback replay returns the same minimized public projection and never exposes pseudonym hash or encrypted comment fields',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo,"return array_intersect_key(\$existing, array_flip(['case_uuid','rating','opted_out','submitted_at']));"));
    $start=strpos($repo,'public function addFeedback(');
    $end=strpos($repo,'public function mergeCases(',$start);
    $block=substr($repo,$start,$end-$start);
    assert(!str_contains($block,'return $existing;'));
});
'''
if needle not in t: raise SystemExit('R41 test marker missing')
t=t.replace(needle,block+needle,1).replace('passed through R40','passed through R41',1)
write(tp,t)
print('R41 correction materialized')
