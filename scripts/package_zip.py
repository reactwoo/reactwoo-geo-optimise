#!/usr/bin/env python3
"""Release zip for ReactWoo Geo Optimise. Output: reactwoo-geo-optimise.zip"""

from __future__ import annotations

import os
import zipfile
from pathlib import Path

ROOT_FOLDER = "reactwoo-geo-optimise"
OUTPUT_ZIP = "reactwoo-geo-optimise.zip"

INCLUDE_DIRS = ["admin", "includes"]

INCLUDE_FILES = [
    "reactwoo-geo-optimise.php",
    "readme.txt",
]


def main() -> None:
    base = Path(__file__).resolve().parent.parent
    out = base / OUTPUT_ZIP
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
                    zf.write(filepath, arcname=f"{ROOT_FOLDER}/{rel}")

        for filename in INCLUDE_FILES:
            filepath = base / filename
            if filepath.is_file():
                zf.write(filepath, arcname=f"{ROOT_FOLDER}/{filename}")

    with zipfile.ZipFile(out, "r") as zf:
        names = zf.namelist()
        if any("\\" in n for n in names) or any(
            n.startswith(f"{ROOT_FOLDER}/{ROOT_FOLDER}/") for n in names
        ):
            raise RuntimeError("Invalid zip structure")

    print(f"Created: {out}")


if __name__ == "__main__":
    main()
