#!/usr/bin/env python3
"""Fail closed when CF-02 release identity drifts across code, docs and workflows."""
from __future__ import annotations
import json,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
M=json.loads((ROOT/'release/manifest.json').read_text(encoding='utf-8'))
V=str(M['plugin_version']); S=str(M['database_schema_version']); C=str(M['contract_version']); P=str(M['plan_version'])

def fail(msg:str)->None:
    raise SystemExit('release-identity-error: '+msg)

def text(path:str)->str:
    p=ROOT/path
    if not p.is_file(): fail(f'missing identity surface: {path}')
    return p.read_text(encoding='utf-8')

plugin=text('cf-02-support-appeals-case-management.php')
checks={
 'plugin header': f' * Version: {V}' in plugin,
 'runtime constant': f"define('CF02_VERSION', '{V}')" in plugin,
 'plan constant': f"define('CF02_PLAN_VERSION', '{P}')" in plugin,
 'readme stable tag': f'Stable tag: {V}' in text('readme.txt'),
 'schema constant': f"public const VERSION = '{S}'" in text('src/Infrastructure/WordPress/SchemaCompletion.php'),
 'contract constant': f"public const CONTRACT_VERSION = '{C}'" in text('src/Contracts/SupportContractCatalog.php'),
}
for label,ok in checks.items():
    if not ok: fail(label+' differs from release manifest')

identity_docs={
 'docs/RELEASE-PROCESS.md':[V,S,C,P],
 'docs/STAGING-ACCEPTANCE.md':[V,S,C],
 'docs/PRODUCTION-READINESS.md':[V,S,C,P],
 'docs/CHANGE-CONTROL.md':[V,S,C],
 'docs/COMPLETE-CODING-CANDIDATE.md':[V,S,C],
 'docs/COMPLETE-RUNTIME-INTEGRATION.md':[V,S],
 'docs/TRACEABILITY.md':[V,S],
 'README.md':[V,S,C],
}
for path,values in identity_docs.items():
    body=text(path)
    for value in values:
        if value not in body: fail(f'{path} does not state current identity {value}')

# Current release/runbook documents may not retain a prior candidate as their operative identity.
for path in ['docs/RELEASE-PROCESS.md','docs/STAGING-ACCEPTANCE.md','docs/PRODUCTION-READINESS.md','docs/CHANGE-CONTROL.md']:
    body=text(path)
    for stale in ['1.0.0-rc.2','1.0.0-rc.3','1.0.0-rc.4','1.0.0-rc.5']:
        if stale != V and stale in body: fail(f'{path} contains stale operative candidate {stale}')

for path in ['.github/workflows/release-candidate.yml','.github/workflows/wordpress-smoke.yml','.github/workflows/wordpress-runtime-smoke.yml']:
    body=text(path)
    if re.search(r'^\s*PACKAGE_VERSION:\s*1\.0\.0-rc\.',body,re.M):
        fail(f'{path} hard-codes PACKAGE_VERSION instead of resolving the manifest')
    if 'Resolve release identity from manifest' not in body:
        fail(f'{path} does not resolve release identity from manifest')

if M.get('release_status')!='three-plan-harmonized-candidate-not-staging-accepted':
    fail('release status is not truthful')
if len(M.get('external_acceptance_gates',[]))<8:
    fail('external acceptance gates are incomplete')
print(json.dumps({'verified':True,'plugin_version':V,'schema':S,'contract':C,'plan':P},sort_keys=True))
