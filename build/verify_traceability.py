#!/usr/bin/env python3
"""Verify complete, file-backed three-plan CF-02 traceability."""
from __future__ import annotations
import json,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PATH=ROOT/'release/traceability.json'

def fail(msg:str)->None: raise SystemExit('traceability-error: '+msg)
def exists(rel:str)->bool: return (ROOT/rel).is_file()
try: data=json.loads(PATH.read_text(encoding='utf-8'))
except Exception as exc: fail(f'cannot read traceability manifest: {exc}')
expected=[f'CF02-FR-{i:03d}' for i in range(1,35)]
reqs=data.get('requirements')
if not isinstance(reqs,list): fail('requirements list missing')
ids=[r.get('id') for r in reqs]
if ids!=expected: fail('requirements must contain exact ordered CF02-FR-001 through CF02-FR-034')
for req in reqs:
    rid=req['id']
    for field in ['source','tests','migration','security_privacy']:
        values=req.get(field)
        if not isinstance(values,list) or not values: fail(f'{rid} has no {field} evidence')
        for rel in values:
            if not exists(rel): fail(f'{rid} references missing {field} file: {rel}')
    if req.get('staging_evidence_status')!='pending-external':
        fail(f'{rid} falsely claims staging evidence')
for group,required in [('central_invariants',{'CENTRAL-OWNER-001','CENTRAL-AUTH-002','CENTRAL-RELEASE-003'}),('all_chats_directives',{'CHAT-BIZ-022','CHAT-GOV-023','CHAT-QA-001','CHAT-VISUAL-GREEN-RTL','CHAT-FILE26-PRIVACY'})]:
    rows=data.get(group)
    if not isinstance(rows,list) or {r.get('id') for r in rows}!=required: fail(f'{group} IDs are incomplete')
    for row in rows:
        for field in ['source','tests']:
            for rel in row.get(field,[]):
                if not exists(rel): fail(f"{row.get('id')} references missing {field} file: {rel}")
if data.get('external_acceptance_gates_status')!='pending-external': fail('external gates must remain pending')
print(json.dumps({'verified':True,'requirements':len(reqs),'central_invariants':3,'all_chats_directives':5},sort_keys=True))
