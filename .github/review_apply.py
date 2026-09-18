from pathlib import Path
ROOT=Path('.')
def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)

p='build/package.py'
s=read(p)
marker='''def source_timestamp(source_sha: str) -> tuple[int, str]:\n'''
helper='''def validate_source_checkout(source_sha: str) -> None:
    head = git_value("rev-parse", "HEAD")
    if head is None:
        fail("release packaging requires an exact Git checkout")
    if head != source_sha:
        fail("source SHA does not match the checked-out commit")
    dirty = git_value("status", "--porcelain", "--untracked-files=no")
    if dirty is None:
        fail("tracked working-tree state could not be verified")
    if dirty != "":
        fail("tracked working tree is dirty; commit corrections before packaging")


'''
if 'def validate_source_checkout(source_sha: str)' not in s:
    idx=s.index(marker)
    s=s[:idx]+helper+s[idx:]
old='''    source_sha = resolve_source_sha(args.source_sha)\n    _, created = source_timestamp(source_sha)\n'''
new='''    source_sha = resolve_source_sha(args.source_sha)\n    validate_source_checkout(source_sha)\n    _, created = source_timestamp(source_sha)\n'''
if old not in s: raise SystemExit('R45 package main marker missing')
s=s.replace(old,new,1)
write(p,s)

cp='composer.json'
c=read(cp)
oldc='''      "php -d zend.assertions=1 tests/c2n-r24-r33.php",\n      "php -d zend.assertions=1 tests/release.php"'''
newc='''      "php -d zend.assertions=1 tests/c2n-r24-r33.php",\n      "php -d zend.assertions=1 tests/c2q-r36-r45.php",\n      "php -d zend.assertions=1 tests/release.php"'''
if oldc not in c: raise SystemExit('R45 composer insertion marker missing')
c=c.replace(oldc,newc,1)
write(cp,c)

warnp='tests/c2n-r24-r33.php'
warn=read(warnp)
warn=warn.replace("assert(!str_contains($ctl,\"'verified' => (bool) $request->get_param('verified')\"));","assert(!str_contains($ctl,\"'verified' => (bool) \\$request->get_param('verified')\"));")
write(warnp,warn)

tp='tests/c2q-r36-r45.php'
t=read(tp)
t=t.replace("$delete=strpos($block,\"$this->wpdb->delete($this->tables['cases']\");","$delete=strpos($block,'$this->wpdb->delete($this->tables[\\'cases\\']');")
needle='if($failures!==[]){exit(1);}'
block=r'''
$test(45,'standard CI executes this review register and packaging cannot label a dirty or different checkout as an exact source SHA',static function()use($read):void{
    $composer=$read('composer.json');
    $package=$read('build/package.py');
    assert(str_contains($composer,'php -d zend.assertions=1 tests/c2q-r36-r45.php'));
    assert(str_contains($package,'def validate_source_checkout(source_sha: str) -> None:'));
    assert(str_contains($package,'git_value("rev-parse", "HEAD")'));
    assert(str_contains($package,'git_value("status", "--porcelain", "--untracked-files=no")'));
    assert(str_contains($package,'tracked working tree is dirty; commit corrections before packaging'));
    assert(str_contains($package,'validate_source_checkout(source_sha)'));
});
'''
if needle not in t: raise SystemExit('R45 test marker missing')
t=t.replace(needle,block+needle,1).replace('passed through R44','passed through R45',1)
write(tp,t)
print('R45 corrections materialized')
