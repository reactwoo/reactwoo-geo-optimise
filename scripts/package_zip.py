#!/usr/bin/env python3
"""Release zip for ReactWoo Geo Optimise. Paths: package.json → reactwooBuild."""

from __future__ import annotations

import json
import os
import zipfile
from pathlib import Path

_DEFAULT_FOLDER = "reactwoo-geo-optimise"

INCLUDE_DIRS = ["admin", "assets", "includes"]

INCLUDE_FILES = [
    "reactwoo-geo-optimise.php",
    "readme.txt",
]


def _zip_paths(base: Path) -> tuple[str, str]:
    pkg_path = base / "package.json"
    zip_name = f"{_DEFAULT_FOLDER}.zip"
    if not pkg_path.is_file():
        return _DEFAULT_FOLDER, zip_name
    try:
        data = json.loads(pkg_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return _DEFAULT_FOLDER, zip_name
    cfg = data.get("reactwooBuild")
    if not isinstance(cfg, dict):
        return _DEFAULT_FOLDER, zip_name
    folder = cfg.get("pluginFolder") or _DEFAULT_FOLDER
    zfile = cfg.get("zipFile") or f"{folder}.zip"
    return str(folder), str(zfile)


def main() -> None:
    base = Path(__file__).resolve().parent.parent
    root_folder, zip_name = _zip_paths(base)
    out = base / zip_name
    if out.exists():
        out.unlink()

    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
        for dirname in INCLUDE_DIRS:
            dirpath = base / dirname
            if not dirpath.is_dir():
                continue
            for root, _dirs, files in os.walk(dirpath):
                for filename in files:
                    filepath = Path(root) / filename
                    rel = filepath.relative_to(base).as_posix()
                    zf.write(filepath, arcname=f"{root_folder}/{rel}")

        for filename in INCLUDE_FILES:
            filepath = base / filename
            if filepath.is_file():
                zf.write(filepath, arcname=f"{root_folder}/{filename}")

    with zipfile.ZipFile(out, "r") as zf:
        names = zf.namelist()
        if any("\\" in n for n in names) or any(
            n.startswith(f"{root_folder}/{root_folder}/") for n in names
        ):
            raise RuntimeError("Invalid zip structure")

    print(f"Created: {out}")


if __name__ == "__main__":
    main()
