#!/usr/bin/env python3
from __future__ import annotations

import base64
import gzip
import json
import shutil
from pathlib import Path

root = Path(__file__).resolve().parents[2]
payload_dir = root / ".github" / "cf02-rc6"
parts = sorted(payload_dir.glob("part*.b64"))
if not parts:
    raise SystemExit("RC6 payload parts are missing")
encoded = "".join(part.read_text(encoding="ascii").strip() for part in parts)
try:
    mapping = json.loads(gzip.decompress(base64.b64decode(encoded, validate=True)).decode("utf-8"))
except Exception as exc:
    raise SystemExit(f"Cannot decode RC6 payload: {exc}")
if not isinstance(mapping, dict) or len(mapping) != 31:
    raise SystemExit("RC6 payload file count is invalid")
for relative, encoded_file in mapping.items():
    target = (root / relative).resolve()
    if root.resolve() not in target.parents:
        raise SystemExit(f"Unsafe payload path: {relative}")
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(base64.b64decode(encoded_file, validate=True))
workflow = root / ".github" / "workflows" / "cf02-rc6-applicator.yml"
if workflow.exists():
    workflow.unlink()
shutil.rmtree(payload_dir)
print(f"Applied {len(mapping)} RC6 files and removed the one-time applicator")
