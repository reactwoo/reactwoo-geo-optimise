#!/usr/bin/env python3
"""Release zip for ReactWoo Geo Optimise. Paths: package.json → reactwooBuild."""

from __future__ import annotations

import json
import os
import re
import zipfile
from pathlib import Path

_DEFAULT_FOLDER = "reactwoo-geo-optimise"

INCLUDE_DIRS = ["admin", "assets", "includes", "merged-geo-ai"]

INCLUDE_FILES = [
    "reactwoo-geo-optimise.php",
    "readme.txt",
]


def _is_ci_environment() -> bool:
    return os.environ.get("CI", "").lower() in ("1", "true", "yes")


def _read_plugin_version(base: Path, cfg: dict, folder: str) -> str | None:
    main_php = cfg.get("mainPhp") or f"{folder}.php"
    php_path = base / str(main_php)
    if not php_path.is_file():
        return None
    try:
        text = php_path.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return None
    match = re.search(r"^\s*\*\s*Version:\s*([^\s\r\n]+)", text, re.MULTILINE)
    return match.group(1).strip() if match else None


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
    version_in_zip = cfg.get("versionInZipFile", True)
    if version_in_zip and not _is_ci_environment():
        version = _read_plugin_version(base, cfg, folder)
        if version:
            stem = Path(zfile).stem
            suffix = Path(zfile).suffix or ".zip"
            zfile = f"{stem}-{version}{suffix}"
    return str(folder), str(zfile)


# Sanesecurity foxhole_js.cdb. Entry numbers are 1-based file positions.
# Sizes are uncompressed and inclusive. JS_Zip_2's published filename regex does
# not match a normal .js name; it is applied here as any .js file in that size
# window, which is the false positive this order avoids.
FOXHOLE_JS_ZIP_RULES: tuple[tuple[str, int, int, int], ...] = (
    ("Sanesecurity.Foxhole.JS_Zip_1", 1, 0, 512_000),
    ("Sanesecurity.Foxhole.JS_Zip_2", 2, 0, 51_200),
    ("Sanesecurity.Foxhole.JS_Zip_4", 4, 90_000, 512_000),
) + tuple(
    (f"Sanesecurity.Foxhole.JS_Zip_{entry}", entry, 3_000, 512_000) for entry in range(11, 25)
)

# Entries that must not be a .js file, whatever its size.
JS_FORBIDDEN_ENTRIES = frozenset({1, 2, 4, *range(11, 25)})


def path_is_javascript(path: str) -> bool:
    """True when the zip member name ends in .js (not .json)."""
    name = path.replace("\\", "/").rstrip("/").rsplit("/", 1)[-1]
    return name.lower().endswith(".js")


def javascript_at_forbidden_entries(paths: list[str]) -> list[int]:
    """1-based positions where a .js file would trip the Foxhole layout."""
    return [
        index
        for index, path in enumerate(paths, start=1)
        if index in JS_FORBIDDEN_ENTRIES and path_is_javascript(path)
    ]


def foxhole_js_zip_matches(entries: list[tuple[str, int]]) -> list[str]:
    """Rule names from foxhole_js.cdb that match this ordered (name, uncompressed size) list."""
    hits: list[str] = []
    for rule, entry, low, high in FOXHOLE_JS_ZIP_RULES:
        index = entry - 1
        if index < 0 or index >= len(entries):
            continue
        filename, size = entries[index]
        if path_is_javascript(filename) and low <= int(size) <= high:
            hits.append(rule)
    return hits


def order_zip_rels(rels: list[str], main_rel: str, readme_rel: str = "readme.txt") -> list[str]:
    """
    Write the plugin bootstrap and readme first, then other non-JS files, then
    JS. Both groups are sorted so two builds of the same tree match. If that
    still leaves a .js file at entry 1, 2, 4, or 11-24, non-JS files fill those
    slots.
    """
    unique: list[str] = []
    seen: set[str] = set()
    for rel in rels:
        rel = rel.replace("\\", "/")
        if rel in seen or rel.endswith("/"):
            continue
        seen.add(rel)
        unique.append(rel)

    head: list[str] = []
    if main_rel in seen:
        head.append(main_rel)
    if readme_rel in seen and readme_rel not in head:
        head.append(readme_rel)
    head_set = set(head)
    non_js = sorted(
        (rel for rel in unique if rel not in head_set and not path_is_javascript(rel)),
        key=str.casefold,
    )
    js = sorted((rel for rel in unique if path_is_javascript(rel)), key=str.casefold)
    preferred = head + non_js + js
    if not javascript_at_forbidden_entries(preferred):
        return preferred

    total = len(preferred)
    slots: list[str | None] = [None] * total
    for index, rel in enumerate(head):
        if index < total:
            slots[index] = rel
    pool = list(non_js)
    for position in range(1, total + 1):
        if slots[position - 1] is not None:
            continue
        if position in JS_FORBIDDEN_ENTRIES and pool:
            slots[position - 1] = pool.pop(0)
    rest = pool + js
    for index in range(total):
        if slots[index] is None and rest:
            slots[index] = rest.pop(0)
    ordered = [rel for rel in slots if rel is not None]
    blocked = javascript_at_forbidden_entries(ordered)
    if blocked or rest:
        listed = ", ".join(str(number) for number in blocked) or "unknown"
        raise RuntimeError(
            "Zip would still place a .js file at Foxhole entry "
            f"{listed}. Entries 1, 2, 4, and 11-24 must not be .js files."
        )
    return ordered


def _plugin_main_rel(base: Path, folder: str) -> str:
    pkg_path = base / "package.json"
    cfg: dict = {}
    if pkg_path.is_file():
        try:
            data = json.loads(pkg_path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            data = {}
        raw = data.get("reactwooBuild")
        if isinstance(raw, dict):
            cfg = raw
    return str(cfg.get("mainPhp") or f"{folder}.php")


def collect_ordered_members(base: Path) -> tuple[str, list[tuple[str, Path]]]:
    """Files the zip will contain, in Foxhole-safe order."""
    root_folder, _zip_name = _zip_paths(base)
    main_rel = _plugin_main_rel(base, root_folder)
    found: dict[str, Path] = {}

    for dirname in INCLUDE_DIRS:
        dirpath = base / dirname
        if not dirpath.is_dir():
            continue
        for root, _dirs, files in os.walk(dirpath):
            for filename in files:
                filepath = Path(root) / filename
                rel = filepath.relative_to(base).as_posix()
                found[rel] = filepath

    for filename in INCLUDE_FILES:
        filepath = base / filename
        if filepath.is_file():
            found[filename] = filepath

    ordered = order_zip_rels(list(found), main_rel, "readme.txt")
    return root_folder, [(rel, found[rel]) for rel in ordered]


def write_ordered_zip(out: Path, root_folder: str, members: list[tuple[str, Path]]) -> None:
    if out.exists():
        out.unlink()
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
        for rel, payload in members:
            zf.write(payload, arcname=f"{root_folder}/{rel}")


def zip_file_entries(zf: zipfile.ZipFile) -> list[tuple[str, int]]:
    """Ordered file members and uncompressed sizes. Directory entries are ignored."""
    return [(info.filename, info.file_size) for info in zf.infolist() if not info.is_dir()]


def assert_zip_avoids_foxhole_js(zf: zipfile.ZipFile) -> None:
    entries = zip_file_entries(zf)
    names = [name for name, _size in entries]
    blocked = javascript_at_forbidden_entries(names)
    hits = foxhole_js_zip_matches(entries)
    if not blocked and not hits:
        return
    detail: list[str] = []
    if blocked:
        detail.append(".js at entries " + ", ".join(str(number) for number in blocked))
    if hits:
        detail.append("rules " + ", ".join(hits))
    raise RuntimeError("Zip trips ClamAV Foxhole JS rules: " + "; ".join(detail))


def main() -> None:
    base = Path(__file__).resolve().parent.parent
    _root_folder, zip_name = _zip_paths(base)
    out = base / zip_name
    root_folder, members = collect_ordered_members(base)
    write_ordered_zip(out, root_folder, members)

    with zipfile.ZipFile(out, "r") as zf:
        assert_zip_avoids_foxhole_js(zf)
        names = zf.namelist()
        if any("\\" in n for n in names) or any(
            n.startswith(f"{root_folder}/{root_folder}/") for n in names
        ):
            raise RuntimeError("Invalid zip structure")

    print(f"Created: {out}")


if __name__ == "__main__":
    main()
