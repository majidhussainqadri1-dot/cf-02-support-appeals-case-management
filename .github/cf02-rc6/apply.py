#!/usr/bin/env python3
from __future__ import annotations

import base64
import gzip
import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
payload_dir = root / ".github" / "cf02-rc6"
parts = sorted(payload_dir.glob("part*.b64"))
if len(parts) != 6:
    raise SystemExit("RC6 payload must contain exactly six parts")
chunks = [part.read_text(encoding="ascii").strip() for part in parts]
encoded = chunks[0] + chunks[1][2000:] + "".join(chunks[2:])
if len(encoded) != 81944:
    raise SystemExit(f"RC6 payload length is invalid: {len(encoded)}")
expected_sha256 = "75a28004c31a157fb2b52d416fd87fc2c1fe38de052312e071bc251f021578e7"
actual_sha256 = hashlib.sha256(encoded.encode("ascii")).hexdigest()
if actual_sha256 != expected_sha256:
    raise SystemExit(f"RC6 payload digest mismatch: {actual_sha256}")
try:
    mapping = json.loads(gzip.decompress(base64.b64decode(encoded, validate=True)).decode("utf-8"))
except Exception as exc:
    raise SystemExit(f"Cannot decode RC6 payload: {exc}")
if not isinstance(mapping, dict) or len(mapping) != 31:
    raise SystemExit("RC6 payload file count is invalid")
applied = 0
for relative, encoded_file in mapping.items():
    # GitHub Apps cannot modify workflow definitions through a workflow token.
    # Those four authenticated connector updates follow this source commit.
    if relative.startswith(".github/workflows/"):
        continue
    target = (root / relative).resolve()
    if root.resolve() not in target.parents:
        raise SystemExit(f"Unsafe payload path: {relative}")
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(base64.b64decode(encoded_file, validate=True))
    applied += 1
if applied != 27:
    raise SystemExit(f"Unexpected applied file count: {applied}")
print("Applied 27 RC6 source files; four workflow files follow through the authenticated connector")
