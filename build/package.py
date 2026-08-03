#!/usr/bin/env python3
"""Build a deterministic, allowlisted CF-02 WordPress release-candidate package."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
import zipfile
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
CONFIG_PATH = ROOT / "release" / "manifest.json"
DIST = ROOT / "dist"
FIXED_ZIP_TIME = (1980, 1, 1, 0, 0, 0)


def fail(message: str) -> "NoReturn":
    raise SystemExit(f"package-error: {message}")


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def git_value(*args: str) -> str | None:
    try:
        return subprocess.check_output(
            ["git", "-C", str(ROOT), *args],
            stderr=subprocess.DEVNULL,
            text=True,
        ).strip()
    except (OSError, subprocess.CalledProcessError):
        return None


def resolve_source_sha(value: str | None) -> str:
    candidate = value or os.environ.get("GITHUB_SHA") or git_value("rev-parse", "HEAD")
    if candidate is None or re.fullmatch(r"[0-9a-f]{40}", candidate) is None:
        fail("a full 40-character lowercase source SHA is required")
    return candidate


def source_timestamp(source_sha: str) -> tuple[int, str]:
    raw = os.environ.get("SOURCE_DATE_EPOCH") or git_value("show", "-s", "--format=%ct", source_sha)
    try:
        epoch = int(raw or "315532800")
    except ValueError:
        fail("SOURCE_DATE_EPOCH must be an integer")
    if epoch < 315532800:
        epoch = 315532800
    rendered = datetime.fromtimestamp(epoch, tz=timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")
    return epoch, rendered


def load_config() -> dict[str, Any]:
    try:
        config = json.loads(CONFIG_PATH.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        fail(f"cannot read release manifest: {exc}")
    required = {
        "package_slug",
        "plugin_version",
        "plan_version",
        "database_schema_version",
        "entrypoint",
        "package_root_files",
        "package_directories",
        "forbidden_package_paths",
    }
    missing = sorted(required - config.keys())
    if missing:
        fail(f"release manifest is missing: {', '.join(missing)}")
    return config


def validate_metadata(config: dict[str, Any]) -> None:
    entrypoint = ROOT / str(config["entrypoint"])
    text = entrypoint.read_text(encoding="utf-8")
    header = re.search(r"^ \* Version:\s*([^\r\n]+)$", text, re.MULTILINE)
    constant = re.search(r"define\('CF02_VERSION',\s*'([^']+)'\);", text)
    plan = re.search(r"define\('CF02_PLAN_VERSION',\s*'([^']+)'\);", text)
    if header is None or constant is None or plan is None:
        fail("plugin version or plan metadata is missing")
    expected = str(config["plugin_version"])
    if header.group(1).strip() != expected or constant.group(1) != expected:
        fail("plugin header, runtime constant and release manifest versions differ")
    if plan.group(1) != str(config["plan_version"]):
        fail("plugin plan version differs from release manifest")

    schema_text = (ROOT / "src" / "Infrastructure" / "WordPress" / "SchemaExtension.php").read_text(encoding="utf-8")
    schema = re.search(r"public const VERSION = '([^']+)';", schema_text)
    if schema is None or schema.group(1) != str(config["database_schema_version"]):
        fail("database schema version differs from release manifest")


def safe_relative(path: Path) -> str:
    relative = path.relative_to(ROOT).as_posix()
    pure = PurePosixPath(relative)
    if pure.is_absolute() or ".." in pure.parts:
        fail(f"unsafe package path: {relative}")
    return relative


def collect_files(config: dict[str, Any]) -> list[Path]:
    files: list[Path] = []
    for item in config["package_root_files"]:
        path = ROOT / str(item)
        if not path.is_file() or path.is_symlink():
            fail(f"required package file is missing or unsafe: {item}")
        files.append(path)

    for item in config["package_directories"]:
        directory = ROOT / str(item)
        if not directory.exists():
            continue
        if not directory.is_dir() or directory.is_symlink():
            fail(f"package directory is unsafe: {item}")
        for path in sorted(directory.rglob("*")):
            if path.is_symlink():
                fail(f"symbolic links are forbidden in packages: {safe_relative(path)}")
            if path.is_file():
                files.append(path)

    unique = sorted({path.resolve() for path in files}, key=lambda path: safe_relative(path))
    forbidden = {str(value).strip("/") for value in config["forbidden_package_paths"]}
    for path in unique:
        relative = safe_relative(path)
        parts = PurePosixPath(relative).parts
        if any(part in forbidden for part in parts):
            fail(f"forbidden package path selected: {relative}")
        if path.stat().st_size > 10 * 1024 * 1024:
            fail(f"package source file exceeds 10 MiB: {relative}")
    return unique


def zip_entry(name: str, data: bytes) -> tuple[zipfile.ZipInfo, bytes]:
    info = zipfile.ZipInfo(name, date_time=FIXED_ZIP_TIME)
    info.compress_type = zipfile.ZIP_DEFLATED
    info.create_system = 3
    info.external_attr = 0o100644 << 16
    return info, data


def build_sbom(config: dict[str, Any], source_sha: str, created: str, file_hashes: dict[str, str]) -> dict[str, Any]:
    package_spdx = "SPDXRef-Package-CF02"
    files = []
    relationships = []
    for index, (name, digest) in enumerate(sorted(file_hashes.items()), start=1):
        spdx_id = f"SPDXRef-File-{index}"
        files.append({
            "SPDXID": spdx_id,
            "fileName": f"./{name}",
            "checksums": [{"algorithm": "SHA256", "checksumValue": digest}],
            "licenseConcluded": "NOASSERTION",
            "copyrightText": "Copyright (c) 2026 Dr. Allamah Majid Hussain Sabri",
        })
        relationships.append({
            "spdxElementId": package_spdx,
            "relationshipType": "CONTAINS",
            "relatedSpdxElement": spdx_id,
        })
    return {
        "spdxVersion": "SPDX-2.3",
        "dataLicense": "CC0-1.0",
        "SPDXID": "SPDXRef-DOCUMENT",
        "name": f"CF-02 {config['plugin_version']} source package SBOM",
        "documentNamespace": f"https://sabrihomeopathy.com/spdx/cf02/{config['plugin_version']}/{source_sha}",
        "creationInfo": {
            "created": created,
            "creators": ["Tool: cf02-deterministic-packager/1"],
        },
        "packages": [{
            "name": str(config["package_slug"]),
            "SPDXID": package_spdx,
            "versionInfo": str(config["plugin_version"]),
            "downloadLocation": "NOASSERTION",
            "filesAnalyzed": True,
            "licenseConcluded": "LicenseRef-Proprietary",
            "licenseDeclared": "LicenseRef-Proprietary",
            "copyrightText": "Copyright (c) 2026 Dr. Allamah Majid Hussain Sabri",
        }],
        "files": files,
        "relationships": relationships,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-sha")
    parser.add_argument("--clean", action="store_true", default=True)
    args = parser.parse_args()

    config = load_config()
    validate_metadata(config)
    source_sha = resolve_source_sha(args.source_sha)
    _, created = source_timestamp(source_sha)
    files = collect_files(config)

    DIST.mkdir(exist_ok=True)
    for old in DIST.glob("*"):
        if old.is_file():
            old.unlink()

    slug = str(config["package_slug"])
    version = str(config["plugin_version"])
    zip_path = DIST / f"{slug}-{version}.zip"

    file_payloads: dict[str, bytes] = {}
    file_hashes: dict[str, str] = {}
    for path in files:
        relative = safe_relative(path)
        data = path.read_bytes()
        file_payloads[relative] = data
        file_hashes[relative] = sha256_bytes(data)

    package_manifest = {
        "manifest_schema": 1,
        "package_slug": slug,
        "plugin_version": version,
        "plan_version": str(config["plan_version"]),
        "database_schema_version": str(config["database_schema_version"]),
        "source_repository": "https://github.com/majidhussainqadri1-dot/cf-02-support-appeals-case-management",
        "source_sha": source_sha,
        "source_timestamp": created,
        "release_status": str(config["release_status"]),
        "files": dict(sorted(file_hashes.items())),
        "external_acceptance_gates": list(config.get("external_acceptance_gates", [])),
    }
    manifest_bytes = (json.dumps(package_manifest, indent=2, sort_keys=True) + "\n").encode("utf-8")

    with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for relative, data in sorted(file_payloads.items()):
            info, payload = zip_entry(f"{slug}/{relative}", data)
            archive.writestr(info, payload)
        info, payload = zip_entry(f"{slug}/package-manifest.json", manifest_bytes)
        archive.writestr(info, payload)

    zip_digest = sha256_file(zip_path)
    sbom = build_sbom(config, source_sha, created, file_hashes)
    sbom_path = DIST / f"{slug}-{version}.spdx.json"
    sbom_path.write_text(json.dumps(sbom, indent=2, sort_keys=True) + "\n", encoding="utf-8")

    provenance = {
        "_type": "https://in-toto.io/Statement/v1",
        "subject": [{"name": zip_path.name, "digest": {"sha256": zip_digest}}],
        "predicateType": "https://slsa.dev/provenance/v1",
        "predicate": {
            "buildDefinition": {
                "buildType": "https://sabrihomeopathy.com/buildtypes/wordpress-plugin-deterministic/v1",
                "externalParameters": {
                    "plugin_version": version,
                    "plan_version": str(config["plan_version"]),
                    "schema_version": str(config["database_schema_version"]),
                },
                "resolvedDependencies": [{
                    "uri": "git+https://github.com/majidhussainqadri1-dot/cf-02-support-appeals-case-management",
                    "digest": {"sha1": source_sha},
                }],
            },
            "runDetails": {
                "builder": {"id": os.environ.get("GITHUB_SERVER_URL", "local") + "/cf02-release-candidate"},
                "metadata": {"invocationId": os.environ.get("GITHUB_RUN_ID", "local"), "startedOn": created, "finishedOn": created},
            },
        },
    }
    provenance_path = DIST / f"{slug}-{version}.provenance.json"
    provenance_path.write_text(json.dumps(provenance, indent=2, sort_keys=True) + "\n", encoding="utf-8")

    checksums = {
        zip_path.name: zip_digest,
        sbom_path.name: sha256_file(sbom_path),
        provenance_path.name: sha256_file(provenance_path),
    }
    (DIST / "SHA256SUMS").write_text(
        "".join(f"{digest}  {name}\n" for name, digest in sorted(checksums.items())),
        encoding="utf-8",
    )

    print(json.dumps({
        "package": str(zip_path.relative_to(ROOT)),
        "sha256": zip_digest,
        "source_sha": source_sha,
        "files": len(file_hashes) + 1,
    }, sort_keys=True))
    return 0


if __name__ == "__main__":
    sys.exit(main())
