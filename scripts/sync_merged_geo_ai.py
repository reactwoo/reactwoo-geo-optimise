#!/usr/bin/env python3
"""Sync Geo AI v0.4.140 bundle into reactwoo-geo-optimise/merged-geo-ai."""

from __future__ import annotations

import shutil
from pathlib import Path

OPT = Path(__file__).resolve().parent.parent
GEO_AI = OPT.parent / "reactwoo-geo-ai"
DEST = OPT / "merged-geo-ai"


def main() -> None:
    if not GEO_AI.is_dir():
        raise SystemExit(f"Geo AI source not found: {GEO_AI}")
    if DEST.exists():
        shutil.rmtree(DEST)
    DEST.mkdir(parents=True)
    shutil.copytree(GEO_AI / "includes", DEST / "includes")
    shutil.copytree(GEO_AI / "admin", DEST / "admin")
    (DEST / "module.php").write_text(
        "<?php\nif ( ! defined( 'ABSPATH' ) ) { exit; }\n",
        encoding="utf-8",
    )
    count = sum(1 for p in DEST.rglob("*") if p.is_file())
    print(f"Synced {count} files into {DEST}")


if __name__ == "__main__":
    main()
