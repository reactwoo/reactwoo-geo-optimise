# Cursor output

## Status
done

## Task
Merge `cursor/sitewide-variant-health-f5dc` (head `08a13a89b3136ce7aef0aa7f5b9ccd11cce65890`) into `cursor/sitewide-screen-redesign-5047` with a merge commit. Keep both the site-wide health fix and the redesigned screen plus New test menu. Version stays 0.5.10.

## Conflicts resolved
- `CHANGELOG.md` and `readme.txt`: one 0.5.10 section with the redesign notes and the health fix.
- `ai-handoff/cursor-output.md`: replaced with this merge note. Both sides described their own task.

`admin/views/tests-list.php` merged without a conflict. It keeps the New test menu and `RWGO_Experiment_Service::admin_variant_health()`.

## Not changed
- PR #13 was not edited.
- Version stays 0.5.10.
- No tag and no publish.
