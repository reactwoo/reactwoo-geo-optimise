# Git push on Windows (agents + Local Sites)

Prevents hung pushes, HTTPS credential prompts, and segfault retries that waste agent time.

## Root causes we hit

| Symptom | Cause | Fix |
|---------|--------|-----|
| `git push` hangs, no output | **HTTPS** remote waiting on Credential Manager GUI | Use **SSH** `origin` |
| Exit `139` or `-1073741819` | Windows Git Bash **segfault** on chained or repeated push | **One command per shell**; retry once only |
| `fork: retry: Resource temporarily unavailable` | Too many parallel git/bash children | **Sequential** pushes, one repo at a time |
| Agent retries same push 3+ times | No stop condition | **Report failure** with repo + remote URL after one hang or two failures |

## Required: SSH remotes

Every ReactWoo repo should use:

```text
git@github.com:reactwoo/<repo>.git
```

Check:

```bash
git remote get-url origin
```

Fix (example — reactwoo-flow):

```bash
git remote set-url origin git@github.com:reactwoo/reactwoo-flow.git
```

**Do not** use plain `git push` over `https://github.com/...` in agent sessions.

## Agent push command (use this)

From the **repo root**, standalone command (never chain with `commit`, `tag`, or `&&`):

```bash
GIT_SSH_COMMAND="ssh -o BatchMode=yes -o ConnectTimeout=15" git push origin main
```

With tag (release — one push, branch + tag):

```bash
GIT_SSH_COMMAND="ssh -o BatchMode=yes -o ConnectTimeout=15" git push origin main "v1.2.3"
```

Or use the helper script (reactwoo-flow):

```bash
bash scripts/push-main.sh
```

## Stop rules (agents)

1. If push produces **no output for 30 seconds** → **stop**. Report: repo name, `git remote get-url origin`, last command. Do not retry the same command in a loop.
2. If exit **139** or **-1073741819** → retry **once** in a **new** standalone command. If it fails again → stop and report.
3. **Never** chain on Windows: `git commit && git tag && git push`.
4. Push **one repo at a time** across the Geo family.
5. After push, verify: `git fetch origin && git status -sb` (should not show `[ahead N]`).

## SSH key

BatchMode fails fast if SSH keys are not loaded. On your machine, ensure `ssh -T git@github.com` works before asking an agent to push.

## Related

- Geo Core releases: `docs/releases-and-git-tags.md`
- Cursor rule: `.cursor/rules/git-push-windows.mdc`
