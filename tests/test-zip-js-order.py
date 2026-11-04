#!/usr/bin/env python3
"""Foxhole JS_Zip rules must not match the release zip entry order."""

from __future__ import annotations

import importlib.util
import io
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SPEC = importlib.util.spec_from_file_location("package_zip", ROOT / "scripts" / "package_zip.py")
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("Could not load scripts/package_zip.py")
PACKAGE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(PACKAGE)

FAILED = 0


def check(label: str, ok: bool) -> None:
    global FAILED
    if ok:
        print(f"ok  - {label}")
        return
    print(f"FAIL - {label}")
    FAILED += 1


def entries_of(archive: zipfile.ZipFile) -> list[tuple[str, int]]:
    return PACKAGE.zip_file_entries(archive)


def write_zip(names_and_sizes: list[tuple[str, int]]) -> zipfile.ZipFile:
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w") as archive:
        for name, size in names_and_sizes:
            archive.writestr(name, b"x" * size)
    buf.seek(0)
    return zipfile.ZipFile(buf)


def test_rules_match_the_published_windows() -> None:
    first = [("reactwoo-geo-optimise/admin/js/admin.js", 100)]
    check(
        "JS_Zip_1 matches a small .js file at entry 1",
        PACKAGE.foxhole_js_zip_matches(first) == ["Sanesecurity.Foxhole.JS_Zip_1"],
    )
    check(
        "JS_Zip_1 ignores a .js file larger than 512000 bytes",
        PACKAGE.foxhole_js_zip_matches([("reactwoo-geo-optimise/admin/js/admin.js", 512_001)]) == [],
    )
    check(
        "JS_Zip_1 ignores .json",
        PACKAGE.foxhole_js_zip_matches([("reactwoo-geo-optimise/admin/js/admin.json", 100)]) == [],
    )

    second = [
        ("reactwoo-geo-optimise/reactwoo-geo-optimise.php", 20),
        ("reactwoo-geo-optimise/admin/js/admin.js", 1_000),
    ]
    check(
        "JS_Zip_2 matches a .js file at entry 2 within 51200 bytes",
        PACKAGE.foxhole_js_zip_matches(second) == ["Sanesecurity.Foxhole.JS_Zip_2"],
    )
    oversized = [
        ("reactwoo-geo-optimise/reactwoo-geo-optimise.php", 20),
        ("reactwoo-geo-optimise/admin/js/admin.js", 51_201),
    ]
    check(
        "JS_Zip_2 ignores a .js file above 51200 bytes",
        "Sanesecurity.Foxhole.JS_Zip_2" not in PACKAGE.foxhole_js_zip_matches(oversized),
    )

    fourth = [
        ("reactwoo-geo-optimise/reactwoo-geo-optimise.php", 10),
        ("reactwoo-geo-optimise/readme.txt", 10),
        ("reactwoo-geo-optimise/includes/class.php", 10),
        ("reactwoo-geo-optimise/admin/js/admin.js", 100_000),
    ]
    check(
        "JS_Zip_4 matches a .js file at entry 4 between 90000 and 512000 bytes",
        PACKAGE.foxhole_js_zip_matches(fourth) == ["Sanesecurity.Foxhole.JS_Zip_4"],
    )
    fourth_small = list(fourth)
    fourth_small[3] = ("reactwoo-geo-optimise/admin/js/admin.js", 1_000)
    check(
        "JS_Zip_4 ignores a small .js file at entry 4",
        PACKAGE.foxhole_js_zip_matches(fourth_small) == [],
    )

    eleventh = [("reactwoo-geo-optimise/file-%02d.php" % index, 10) for index in range(10)]
    eleventh.append(("reactwoo-geo-optimise/admin/js/admin.js", 4_000))
    check(
        "JS_Zip_11 matches a .js file of 3000-512000 bytes at entry 11",
        PACKAGE.foxhole_js_zip_matches(eleventh) == ["Sanesecurity.Foxhole.JS_Zip_11"],
    )
    eleventh_tiny = list(eleventh)
    eleventh_tiny[10] = ("reactwoo-geo-optimise/admin/js/admin.js", 100)
    check(
        "a tiny .js file at entry 11 is outside the size window",
        PACKAGE.foxhole_js_zip_matches(eleventh_tiny) == [],
    )
    check(
        "entry 11 is still a forbidden .js position",
        PACKAGE.javascript_at_forbidden_entries([name for name, _size in eleventh_tiny]) == [11],
    )

    twenty_fourth = [("reactwoo-geo-optimise/file-%02d.php" % index, 10) for index in range(23)]
    twenty_fourth.append(("reactwoo-geo-optimise/admin/js/admin.js", 4_000))
    check(
        "JS_Zip_24 matches a .js file at entry 24",
        PACKAGE.foxhole_js_zip_matches(twenty_fourth) == ["Sanesecurity.Foxhole.JS_Zip_24"],
    )

    with write_zip(first) as archive:
        stored = entries_of(archive)
    check(
        "a built zip reports the same JS_Zip_1 hit",
        PACKAGE.foxhole_js_zip_matches(stored) == ["Sanesecurity.Foxhole.JS_Zip_1"],
    )


def test_order_is_stable_and_safe() -> None:
    rels = [
        "admin/js/admin.js",
        "admin/css/admin.css",
        "includes/class-rwgo-plugin.php",
        "readme.txt",
        "reactwoo-geo-optimise.php",
        "assets/icon.svg",
        "admin/js/rules.js",
    ]
    rels.extend(f"includes/part-{index:02d}.php" for index in range(30))
    ordered = PACKAGE.order_zip_rels(rels, "reactwoo-geo-optimise.php", "readme.txt")
    reversed_order = PACKAGE.order_zip_rels(list(reversed(rels)), "reactwoo-geo-optimise.php", "readme.txt")
    check("plugin bootstrap is entry 1", ordered[0] == "reactwoo-geo-optimise.php")
    check("readme.txt is entry 2", ordered[1] == "readme.txt")
    check("order does not depend on walk order", ordered == reversed_order)
    check("no .js file sits on a forbidden entry", PACKAGE.javascript_at_forbidden_entries(ordered) == [])
    first_js = next(index for index, rel in enumerate(ordered) if rel.lower().endswith(".js"))
    check(
        "every .js file is after the non-JS files",
        all(not rel.lower().endswith(".js") for rel in ordered[:first_js]),
    )

    packed = [(f"reactwoo-geo-optimise/{rel}", 4_000 if rel.endswith(".js") else 20) for rel in ordered]
    check("ordered names do not match a Foxhole rule", PACKAGE.foxhole_js_zip_matches(packed) == [])

    with write_zip(packed) as archive:
        built = entries_of(archive)
    check("a zip written in that order does not match a Foxhole rule", PACKAGE.foxhole_js_zip_matches(built) == [])

    scarce = ["plugin.php", "readme.txt", "admin/js/a.js", "admin/js/b.js", "includes/only.php"]
    placed = PACKAGE.order_zip_rels(scarce, "plugin.php", "readme.txt")
    check(
        "a short archive still keeps .js off entries 1, 2, and 4",
        PACKAGE.javascript_at_forbidden_entries(placed) == [],
    )
    check("short archive still starts with the bootstrap and readme", placed[:2] == ["plugin.php", "readme.txt"])

    blocked = False
    try:
        PACKAGE.order_zip_rels(
            ["plugin.php", "readme.txt"] + [f"admin/js/{index}.js" for index in range(20)],
            "plugin.php",
        )
    except RuntimeError:
        blocked = True
    check("refuses an archive that cannot avoid a .js file at entry 4", blocked)


def test_packaged_tree() -> None:
    root, members = PACKAGE.collect_ordered_members(ROOT)
    again = [rel for rel, _payload in PACKAGE.collect_ordered_members(ROOT)[1]]
    rels = [rel for rel, _payload in members]
    check("bootstrap is first", rels[0] == "reactwoo-geo-optimise.php")
    check("readme.txt is second", rels[1] == "readme.txt")
    check("collected order stays the same between builds", rels == again)
    check("no .js file on a forbidden entry", PACKAGE.javascript_at_forbidden_entries(rels) == [])
    first_js = next((index for index, rel in enumerate(rels) if rel.lower().endswith(".js")), None)
    check("JavaScript is written after the other files", first_js is not None and first_js >= 24)
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as archive:
        for rel, payload in members:
            archive.write(payload, arcname=f"{root}/{rel}")
    buf.seek(0)
    with zipfile.ZipFile(buf) as archive:
        hits = PACKAGE.foxhole_js_zip_matches(PACKAGE.zip_file_entries(archive))
        check("built zip matches no Foxhole JS_Zip rule", hits == [])
        raised = False
        try:
            PACKAGE.assert_zip_avoids_foxhole_js(archive)
        except RuntimeError as exc:
            raised = True
            print(f"FAIL - packaged zip guard: {exc}")
            global FAILED
            FAILED += 1
        if not raised:
            print("ok  - packaged zip passes the Foxhole guard")


def main() -> None:
    test_rules_match_the_published_windows()
    test_order_is_stable_and_safe()
    test_packaged_tree()
    if FAILED:
        print(f"{FAILED} failed")
        sys.exit(1)
    print("zip JS entry order checks passed")


if __name__ == "__main__":
    main()
