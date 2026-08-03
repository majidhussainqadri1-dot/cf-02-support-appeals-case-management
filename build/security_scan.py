#!/usr/bin/env python3
"""Fail closed on obvious secrets, private key material and unsafe release files."""

from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[1]
FORBIDDEN_BASENAMES = {
    ".env",
    "id_rsa",
    "id_ed25519",
    "credentials.json",
    "service-account.json",
    "wp-config.php",
}
FORBIDDEN_SUFFIXES = {".pem", ".p12", ".pfx", ".key", ".jks", ".keystore"}
CONTENT_PATTERNS = {
    "private-key": re.compile(r"-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----"),
    "aws-access-key": re.compile(r"\bAKIA[0-9A-Z]{16}\b"),
    "github-token": re.compile(r"\bgh[pousr]_[A-Za-z0-9]{36,255}\b"),
    "slack-token": re.compile(r"\bxox[baprs]-[A-Za-z0-9-]{10,}\b"),
    "stripe-live-key": re.compile(r"\b(?:sk|rk)_live_[A-Za-z0-9]{16,}\b"),
    "google-api-key": re.compile(r"\bAIza[0-9A-Za-z_-]{35}\b"),
}


def tracked_files() -> list[Path]:
    try:
        output = subprocess.check_output(
            ["git", "-C", str(ROOT), "ls-files", "-z"],
            stderr=subprocess.DEVNULL,
        )
    except (OSError, subprocess.CalledProcessError) as exc:
        raise SystemExit(f"security-scan-error: cannot enumerate tracked files: {exc}")
    return [ROOT / raw.decode("utf-8") for raw in output.split(b"\0") if raw]


def main() -> int:
    findings: list[str] = []
    for path in tracked_files():
        relative = path.relative_to(ROOT).as_posix()
        pure = PurePosixPath(relative)
        lowered = pure.name.lower()
        if lowered in FORBIDDEN_BASENAMES or pure.suffix.lower() in FORBIDDEN_SUFFIXES:
            findings.append(f"forbidden-file:{relative}")
            continue
        if not path.is_file() or path.stat().st_size > 5 * 1024 * 1024:
            continue
        try:
            text = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue
        for name, pattern in CONTENT_PATTERNS.items():
            if pattern.search(text):
                findings.append(f"{name}:{relative}")

    if findings:
        for finding in sorted(findings):
            print(finding, file=sys.stderr)
        return 1
    print("Public repository safety scan passed: no obvious secret or private-key material detected.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
