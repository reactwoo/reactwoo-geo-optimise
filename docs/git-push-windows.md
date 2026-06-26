# Git push on Windows (agents + Local Sites)

Automated push with **structured diagnostics** — fix root causes, do not blind-retry.

## Use this (agents)

From **repo root**:

```bash
python scripts/git_push.py
```

Release (branch + tag, one push):

```bash
python scripts/git_push.py --ref v1.2.3
```

Multi-repo (Geo family, stops on first failure):

```bash
python scripts/push_geo_family.py
```

Preflight only (no push):

```bash
python scripts/git_push.py --diagnose-only
```

## What the script does automatically

1. Detects **HTTPS** `origin` → converts to **SSH** (`--fix-https-remote`, default on).
2. Tests **SSH to GitHub** before push (fails fast with `SSH_AUTH` if keys missing).
3. Skips push if **not ahead** of origin.
4. Runs `git push` with **60s timeout** (classifies `GIT_HUNG` instead of hanging forever).
5. On failure, prints **`=== GIT PUSH DIAGNOSTIC ===`** with:
   - `failure_class` (e.g. `SSH_AUTH`, `HTTPS_REMOTE`, `GIT_SEGFAULT`, `FORK_EXHAUSTED`)
   - `recommended_fix` (actionable next step)
   - `push_output` / `push_stderr`

## Agent rules on failure

1. **Read the diagnostic block** — do not retry the same command until `recommended_fix` is applied.
2. **Fix the root cause** (SSH key, remote URL, fork exhaustion, etc.), then run `git_push.py` again.
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
