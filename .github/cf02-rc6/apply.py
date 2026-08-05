#!/usr/bin/env python3
from __future__ import annotations

import base64
import gzip
import hashlib
import json
import shutil
from pathlib import Path

root = Path(__file__).resolve().parents[2]
payload_dir = root / ".github" / "cf02-rc6"
parts = sorted(payload_dir.glob("part*.b64"))
if len(parts) != 6:
    raise SystemExit("RC6 payload must contain exactly six parts")
chunks = [part.read_text(encoding="ascii").strip() for part in parts]
# The first transport chunk is 10,000 characters and the second begins at
# offset 8,000. Remove the deliberate 2,000-character overlap, then append
# the remaining contiguous chunks (offsets 24,000 onward).
encoded = chunks[0] + chunks[1][2000:] + "".join(chunks[2:])
if len(encoded) != 81944:
    raise SystemExit(f"RC6 payload length is invalid: {len(encoded)}")
expected_sha256 = "ee7c96250a41f70e7564f745bf4c0555a5be845f600aac88d28908f3fcd119b2"
actual_sha256 = hashlib.sha256(encoded.encode("ascii")).hexdigest()
if actual_sha256 != expected_sha256:
    raise SystemExit(f"RC6 payload digest mismatch: {actual_sha256}")
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
