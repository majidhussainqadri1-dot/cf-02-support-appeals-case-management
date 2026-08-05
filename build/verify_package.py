#!/usr/bin/env python3
"""Verify CF-02 package layout, hashes, metadata, safety and reproducibility inputs."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import zipfile
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[1]
CONFIG = json.loads((ROOT / "release" / "manifest.json").read_text(encoding="utf-8"))
DIST = ROOT / "dist"


def fail(message: str) -> "NoReturn":
    raise SystemExit(f"verify-error: {message}")


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--package")
    args = parser.parse_args()

    if re.fullmatch(r"[0-9a-f]{40}", args.source_sha) is None:
        fail("source SHA must be a full lowercase Git commit SHA")

    slug = str(CONFIG["package_slug"])
    version = str(CONFIG["plugin_version"])
    package = Path(args.package) if args.package else DIST / f"{slug}-{version}.zip"
    if not package.is_file():
        fail(f"package not found: {package}")

    forbidden = {str(value).strip("/") for value in CONFIG["forbidden_package_paths"]}
    with zipfile.ZipFile(package, "r") as archive:
        names = archive.namelist()
        if names != sorted(names):
            fail("archive entries are not deterministically sorted")
        if not names:
            fail("archive is empty")
        roots = {PurePosixPath(name).parts[0] for name in names}
        if roots != {slug}:
            fail("archive must contain exactly one canonical top-level folder")
        if len(names) != len(set(names)):
            fail("archive contains duplicate paths")

        for info in archive.infolist():
            path = PurePosixPath(info.filename)
            if path.is_absolute() or ".." in path.parts:
                fail(f"unsafe archive path: {info.filename}")
            if any(part in forbidden for part in path.parts[1:]):
                fail(f"forbidden archive path: {info.filename}")
            unix_mode = (info.external_attr >> 16) & 0o170000
            if unix_mode == 0o120000:
                fail(f"symbolic link forbidden: {info.filename}")
            if info.file_size > 10 * 1024 * 1024:
                fail(f"oversized archive member: {info.filename}")

        manifest_name = f"{slug}/package-manifest.json"
        if manifest_name not in names:
            fail("package manifest is missing")
        manifest = json.loads(archive.read(manifest_name).decode("utf-8"))
        expected_pairs = {
            "package_slug": slug,
            "plugin_version": version,
            "plan_version": str(CONFIG["plan_version"]),
            "contract_version": str(CONFIG["contract_version"]),
            "database_schema_version": str(CONFIG["database_schema_version"]),
            "source_sha": args.source_sha,
            "release_status": str(CONFIG["release_status"]),
        }
        for key, expected in expected_pairs.items():
            if manifest.get(key) != expected:
                fail(f"package manifest mismatch for {key}")

        listed = manifest.get("files")
        if not isinstance(listed, dict) or not listed:
            fail("package file hash map is missing")
        actual_payload_names = {name[len(slug) + 1:] for name in names if name != manifest_name}
        if set(listed) != actual_payload_names:
            fail("package manifest file list does not match archive contents")
        for relative, expected_digest in listed.items():
            data = archive.read(f"{slug}/{relative}")
            if sha256(data) != expected_digest:
                fail(f"package payload hash mismatch: {relative}")

        entrypoint = archive.read(f"{slug}/{CONFIG['entrypoint']}").decode("utf-8")
        readme = archive.read(f"{slug}/readme.txt").decode("utf-8")
        uninstall = archive.read(f"{slug}/uninstall.php").decode("utf-8")
        if f"Version: {version}" not in entrypoint or f"define('CF02_VERSION', '{version}')" not in entrypoint:
            fail("entrypoint version metadata differs from release version")
        if f"Stable tag: {version}" not in readme:
            fail("WordPress readme stable tag differs from release version")
        forbidden_uninstall = ["DROP TABLE", "delete_option('cf02_", 'delete_user_meta(', 'DELETE FROM']
        for token in forbidden_uninstall:
            if token.lower() in uninstall.lower():
                fail(f"default uninstall contains destructive operation: {token}")

    expected_checksum = None
    checksums_path = DIST / "SHA256SUMS"
    if checksums_path.is_file():
        for line in checksums_path.read_text(encoding="utf-8").splitlines():
            digest, _, name = line.partition("  ")
            if name == package.name:
                expected_checksum = digest
                break
    if expected_checksum is None:
        fail("package checksum is absent from SHA256SUMS")
    if sha256(package.read_bytes()) != expected_checksum:
        fail("package checksum does not match SHA256SUMS")

    sbom_path = DIST / f"{slug}-{version}.spdx.json"
    provenance_path = DIST / f"{slug}-{version}.provenance.json"
    for evidence in (sbom_path, provenance_path):
        if not evidence.is_file():
            fail(f"release evidence is missing: {evidence.name}")
        expected = None
        for line in checksums_path.read_text(encoding="utf-8").splitlines():
            digest, _, name = line.partition("  ")
            if name == evidence.name:
                expected = digest
                break
        if expected is None or sha256(evidence.read_bytes()) != expected:
            fail(f"release evidence checksum mismatch: {evidence.name}")

    sbom = json.loads(sbom_path.read_text(encoding="utf-8"))
    if sbom.get("spdxVersion") != "SPDX-2.3":
        fail("SBOM is not SPDX 2.3")
    packages = sbom.get("packages") or []
    if len(packages) != 1 or packages[0].get("versionInfo") != version:
        fail("SBOM package version differs from release manifest")

    provenance = json.loads(provenance_path.read_text(encoding="utf-8"))
    subjects = provenance.get("subject") or []
    if len(subjects) != 1 or subjects[0].get("name") != package.name or subjects[0].get("digest", {}).get("sha256") != expected_checksum:
        fail("provenance subject does not bind the verified package")
    definition = provenance.get("predicate", {}).get("buildDefinition", {})
    parameters = definition.get("externalParameters", {})
    expected_parameters = {
        "plugin_version": version,
        "plan_version": str(CONFIG["plan_version"]),
        "schema_version": str(CONFIG["database_schema_version"]),
        "contract_version": str(CONFIG["contract_version"]),
        "release_status": str(CONFIG["release_status"]),
    }
    for key, expected in expected_parameters.items():
        if parameters.get(key) != expected:
            fail(f"provenance parameter mismatch: {key}")
    dependencies = definition.get("resolvedDependencies") or []
    if len(dependencies) != 1 or dependencies[0].get("digest", {}).get("sha1") != args.source_sha:
        fail("provenance source SHA differs from the verified source")

    print(json.dumps({
        "verified": True,
        "package": package.name,
        "version": version,
        "source_sha": args.source_sha,
        "sha256": expected_checksum,
    }, sort_keys=True))
    return 0


if __name__ == "__main__":
    sys.exit(main())
