# Cursor output

## Status
done

## Task
Make `python scripts/package_zip.py` of a given tree byte-identical to the zip `.github/workflows/publish-update.yml` uploads. Do not bump the plugin version, and do not tag or publish.

## Root cause
`write_ordered_zip` called `ZipFile.write()`, which stores each file's filesystem mtime (`ZipInfo.from_file` → `time.localtime(st.st_mtime)`). The publish workflow used `python-version: "3.x"`. A timestamp-only `touch` of the same tree changed the SHA-256 and left names, CRCs, sizes, and order identical. That matches the v0.5.9 published zip (`e5589cff…`) versus a local build (`9dcf31df…`).

## Fix
Entries are written with `writestr` and a stable `ZipInfo`: `SOURCE_DATE_EPOCH` when set and at least 1980-01-01, otherwise 1980-01-01 00:00:00 UTC, Unix creator, mode 0644, deflate level 9. Entry order and the `reactwoo-geo-optimise/` root are unchanged. The publish workflow pins CPython 3.12, does not install Composer dependencies, and fails if a second `package_zip.py` run hashes differently. `tests/test-zip-reproducible.py` packages `git archive HEAD` twice, with and without `CI=true`.

## Files changed
- `scripts/package_zip.py` — fixed zip metadata.
- `tests/test-zip-reproducible.py` — reproducibility contract.
- `.github/workflows/publish-update.yml` — Python 3.12 and the build-twice check.
- `package.json` — `test:zip-reproducible`.

## Not changed
- Plugin version, readme Stable tag, and `RWGO_VERSION` stay 0.5.9.
- No tag and no publish.
- Foxhole entry order and the included paths (`admin/`, `assets/`, `includes/`, `merged-geo-ai/`, main PHP, `readme.txt`).

## Commands
- Before the fix, a timestamp-only `touch` changed the zip SHA-256 and left names, CRCs, and sizes identical.
- `python3 tests/test-zip-reproducible.py` passed. `git archive HEAD` with and without `CI=true` hashed to `de7578a4abb2df3e0905df48f168e00518b2cc70c6e69cffa1422e4708a75107`.
- Two consecutive `python3 scripts/package_zip.py` runs on this branch, then two with `CI=true`, all hashed to that same value. `python3 tests/test-zip-js-order.py` passed.

## Remaining errors
None from the zip checks.
