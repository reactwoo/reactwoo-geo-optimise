# Git push on Windows (agents + Local Sites)

## Default (releases)

Remotes are already SSH (`git@github.com:…`). Use normal git — **push is required** for R2 publish:

```bash
git commit -m "…"
git tag -a "vVERSION" -m "Release VERSION"
git push origin main "vVERSION"
git ls-remote --tags origin "vVERSION"
```

A local tag without push does **not** run `publish-update.yml`.

## Fallback: diagnostic script

If plain `git push` fails or hangs, use **`scripts/git_push.py`** (60s timeout + structured diagnostics):

```bash
python scripts/git_push.py --ref v1.2.3
```

Preflight only: `python scripts/git_push.py --diagnose-only`

## What the script does

1. Detects **HTTPS** `origin` → optional convert to SSH (`--fix-https-remote`, **off** by default).
2. Optional SSH test with `--diagnose-only` only.
3. Pushes if branch is **ahead** and/or **`--ref` tag is missing on origin** (plain `git push`, no SSH env overrides).
4. Runs `git push` with **60s timeout** (classifies `GIT_HUNG` instead of hanging forever).
5. On failure, prints **`=== GIT PUSH DIAGNOSTIC ===`** with `failure_class` and `recommended_fix`.

## Agent rules on failure

1. **Read the diagnostic block** — do not retry the same command until `recommended_fix` is applied.
2. **Fix the root cause** (SSH key, remote URL, fork exhaustion, etc.), then retry plain push or `git_push.py`.
3. **Do not** loop plain `git push`, background hung pushes, or `cmd.exe` workarounds.
4. **One repo at a time** unless using `push_geo_family.py` (already sequential).

## Failure classes

| Class | Meaning | Typical fix |
|-------|---------|-------------|
| `HTTPS_REMOTE` | HTTPS origin (Credential Manager hang) | Auto-fixed to SSH; if not, `git remote set-url origin git@github.com:reactwoo/<repo>.git` |
| `SSH_AUTH` | No SSH key for BatchMode | `ssh -T git@github.com`, `ssh-add`, start ssh-agent |
| `SSH_NETWORK` | Timeout reaching GitHub | Network/VPN/firewall |
| `GIT_HUNG` | Push exceeded 60s | Kill stuck git; ensure SSH remote |
| `GIT_SEGFAULT` | Windows Git Bash crash (139 / -1073741819) | Fresh shell; close extra terminals |
| `FORK_EXHAUSTED` | `fork: Resource temporarily unavailable` | Sequential pushes; close hung agents |
| `PUSH_REJECTED` | Non-fast-forward | `git fetch`, pull/rebase, then push |

## SSH remotes

Every ReactWoo repo should use:

```text
git@github.com:reactwoo/<repo>.git
```

## Related

- Cursor rule: `.cursor/rules/git-push-windows.mdc`
- Geo Core releases: `docs/releases-and-git-tags.md`
- Wrapper: `bash scripts/push-main.sh` → calls `git_push.py`
