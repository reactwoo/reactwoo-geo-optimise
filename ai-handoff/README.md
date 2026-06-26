# AI handoff — file bridge (no API)

Use these markdown files to pass context between **ChatGPT/Codex** (planner/reviewer) and **Cursor** (editor/patch applier) without OpenAI API integration.

## Loop

1. **Planner** writes or updates `current-task.md` (from spec, debug session, or ReactWoo Flow export).
2. **You** paste `current-task.md` into Cursor (or open the repo and say: “Read `ai-handoff/current-task.md` and implement.”).
3. **Cursor** implements, then **must** update `cursor-output.md`.
4. **You** run tests locally and paste failures into `test-output.md` (last ~80 lines only).
5. **Planner** receives only `cursor-output.md` + `test-output.md` + your question — not full chat history.

## Paste-back format (to ChatGPT/Codex)

```markdown
## Cursor Output
[paste ai-handoff/cursor-output.md]

## Test Output
[paste failing command + last 80 lines]

## Current Question
What should be done next?
```

## Files

| File | Owner | Purpose |
|------|-------|---------|
| `current-task.md` | Planner | Problem, expected, acceptance test, do-not-touch |
| `cursor-output.md` | Cursor | Files changed, commands run, remaining errors |
| `test-output.md` | Human | Local test/build output for next planner turn |
| `known-issues.md` | Both | Debug journal — do not repeat failed fixes |
| `decisions.md` | Planner | Architecture choices that outlive one task |

## Copy to another plugin repo

From this repo root:

```bash
python scripts/init-ai-handoff.py --target /path/to/other-plugin-repo
```

Or copy the `ai-handoff/` folder and `.cursor/rules/ai-handoff.mdc` manually.

See `docs/ai-handoff-workflow.md` for the full workflow.
