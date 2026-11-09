#!/usr/bin/env python3
"""The publish zip is two identical runs of scripts/package_zip.py on the git tree."""

from __future__ import annotations

import hashlib
import os
import subprocess
import sys
import tempfile
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
FAILED = 0
PLUGIN_SLUG = "reactwoo-geo-optimise"
_ZIP_EPOCH = (1980, 1, 1, 0, 0, 0)


def check(label: str, ok: bool) -> None:
    global FAILED
    if ok:
        print(f"ok  - {label}")
        return
    print(f"FAIL - {label}")
    FAILED += 1


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def test_workflows_call_package_zip() -> None:
    publish = (ROOT / ".github/workflows/publish-update.yml").read_text(encoding="utf-8")

    check("publish workflow calls package_zip.py", "\n        run: python scripts/package_zip.py\n" in publish)
    check(
        "publish workflow does not install Composer dependencies before packaging",
        "composer install" not in publish,
    )
    check("publish workflow pins CPython 3.12", 'python-version: "3.12"' in publish)
    check(
        "publish workflow compares two package_zip.py runs",
        "package_zip.py produced different zips for the same tree." in publish,
    )
    check(
        "publish artifact key is unchanged",
        'ARTIFACT_KEY="plugins/${PLUGIN_SLUG}/${VERSION}/${PLUGIN_SLUG}.zip"' in publish,
    )
    check(
        "publish latest key is unchanged",
        'LATEST_KEY="plugins/${PLUGIN_SLUG}/latest/${PLUGIN_SLUG}.zip"' in publish,
    )
    check(
        "publish upload uses the unversioned zip name",
        'aws s3 cp "${PLUGIN_SLUG}.zip"' in publish,
    )
    check("publish workflow does not call git archive", "git archive" not in publish)
    check(
        "publish artifact upload still uses the unversioned zip",
        "path: reactwoo-geo-optimise.zip" in publish,
    )


def _package(dest: Path, ci: bool, source_date_epoch: str | None = None) -> None:
    env = os.environ.copy()
    env.pop("SOURCE_DATE_EPOCH", None)
    if source_date_epoch is not None:
        env["SOURCE_DATE_EPOCH"] = source_date_epoch
    if ci:
        env["CI"] = "true"
    else:
        env.pop("CI", None)
    subprocess.run([sys.executable, "scripts/package_zip.py"], cwd=dest, env=env, check=True)


def _extract_head(dest: Path) -> None:
    archive = subprocess.check_output(["git", "-C", str(ROOT), "archive", "HEAD"])
    subprocess.run(["tar", "-x", "-C", str(dest)], input=archive, check=True)


def test_git_tree_zip_is_reproducible() -> None:
    """Package HEAD, which is the tree the publish job checks out."""
    with tempfile.TemporaryDirectory() as tmp:
        dest = Path(tmp) / "tree"
        dest.mkdir()
        _extract_head(dest)

        _package(dest, ci=True)
        publish_zip = dest / f"{PLUGIN_SLUG}.zip"
        check("CI package_zip.py writes reactwoo-geo-optimise.zip", publish_zip.is_file())
        if not publish_zip.is_file():
            return
        first = sha256(publish_zip)
        with zipfile.ZipFile(publish_zip) as archive_zip:
            infos = archive_zip.infolist()
            names = [info.filename for info in infos]
            info = archive_zip.getinfo(f"{PLUGIN_SLUG}/{PLUGIN_SLUG}.php")
        check("publish zip timestamp is fixed at 1980-01-01", info.date_time == _ZIP_EPOCH)
        check("publish zip permissions are 0644", (info.external_attr >> 16) & 0o777 == 0o644)
        check(
            "every entry uses the fixed timestamp",
            {item.date_time for item in infos} == {_ZIP_EPOCH},
        )
        check(
            "every entry is mode 0644",
            {(item.external_attr >> 16) & 0o777 for item in infos} == {0o644},
        )
        check("every entry records a Unix creator", {item.create_system for item in infos} == {3})
        check("top-level folder is reactwoo-geo-optimise/", all(name.startswith(f"{PLUGIN_SLUG}/") for name in names))
        check("bootstrap is the first entry", names[0] == f"{PLUGIN_SLUG}/{PLUGIN_SLUG}.php")
        check("readme.txt is the second entry", names[1] == f"{PLUGIN_SLUG}/readme.txt")
        check("zip has no directory entries", all(not name.endswith("/") for name in names))

        for path in dest.rglob("*"):
            if path.is_file() and path.suffix != ".zip":
                os.utime(path, (1_600_000_000, 1_600_000_000))
        _package(dest, ci=True)
        check("filesystem mtimes do not change the publish zip", sha256(publish_zip) == first)
        with zipfile.ZipFile(publish_zip) as archive_zip:
            check("entry order is unchanged after an mtime rewrite", archive_zip.namelist() == names)

        _package(dest, ci=True)
        second = sha256(publish_zip)
        check("two CI package_zip.py runs share a SHA-256", first == second)
        print(f"publish zip SHA-256 {first}")

        _package(dest, ci=False)
        local_zips = sorted(dest.glob(f"{PLUGIN_SLUG}-*.zip"))
        local_zip = local_zips[0] if local_zips else publish_zip
        check("local package_zip.py writes a versioned zip", local_zip.name != publish_zip.name and local_zip.is_file())
        check("local package_zip.py bytes match the publish zip", sha256(local_zip) == first)

        _package(dest, ci=False)
        again = sorted(dest.glob(f"{PLUGIN_SLUG}-*.zip"))
        check("two local package_zip.py runs share a SHA-256", bool(again) and sha256(again[0]) == first)

        stamped = dest / "stamped.zip"
        _package(dest, ci=True, source_date_epoch="1700000000")
        stamped_hash = sha256(publish_zip)
        with zipfile.ZipFile(publish_zip) as archive_zip:
            stamped_time = archive_zip.getinfo(f"{PLUGIN_SLUG}/{PLUGIN_SLUG}.php").date_time
        check("SOURCE_DATE_EPOCH changes the zip timestamp", stamped_time == (2023, 11, 14, 22, 13, 20))
        check("SOURCE_DATE_EPOCH changes the zip bytes", stamped_hash != first)
        publish_zip.replace(stamped)
        _package(dest, ci=True, source_date_epoch="1700000000")
        check("the same SOURCE_DATE_EPOCH repeats the zip bytes", sha256(publish_zip) == stamped_hash)


def main() -> None:
    test_workflows_call_package_zip()
    test_git_tree_zip_is_reproducible()
    if FAILED:
        print(f"{FAILED} failed")
        sys.exit(1)
    print("publish zip reproducibility checks passed")


if __name__ == "__main__":
    main()
