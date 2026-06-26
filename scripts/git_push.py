#!/usr/bin/env python3
"""
Automated git push with preflight checks and structured failure diagnostics.

Usage (from repo root):
  python scripts/git_push.py
  python scripts/git_push.py --ref v1.2.3          # release: main + tag
  python scripts/git_push.py --diagnose-only
  python scripts/git_push.py --repo /path/to/repo

Exit codes:
  0  push OK (or nothing to push)
  1  push failed — diagnostic printed to stdout
  2  preflight failed before push attempt
"""

from __future__ import annotations

import argparse
import re
import subprocess
import sys
from pathlib import Path

PUSH_TIMEOUT_SEC = 60
SSH_TEST_TIMEOUT_SEC = 15


def run(
    cmd: list[str],
    *,
    cwd: Path,
    timeout: int | None = None,
    env: dict[str, str] | None = None,
) -> subprocess.CompletedProcess[str]:
    merged = None
    if env:
        import os

        merged = os.environ.copy()
        merged.update(env)
    return subprocess.run(
        cmd,
        cwd=cwd,
        capture_output=True,
        text=True,
        timeout=timeout,
        env=merged,
    )


def ssh_env() -> dict[str, str]:
    return {"GIT_SSH_COMMAND": "ssh -o BatchMode=yes -o ConnectTimeout=15"}


def repo_name(root: Path) -> str:
    return root.name


def git_remote(root: Path) -> str:
    proc = run(["git", "remote", "get-url", "origin"], cwd=root)
    if proc.returncode != 0:
        return ""
    return proc.stdout.strip()


def https_to_ssh(url: str) -> str | None:
    if url.startswith("git@"):
        return None
    m = re.match(r"https://github\.com/(.+?)(?:\.git)?/?$", url)
    if not m:
        return None
    return f"git@github.com:{m.group(1)}.git"


def git_status_short(root: Path) -> str:
    proc = run(["git", "status", "-sb"], cwd=root)
    return proc.stdout.strip().splitlines()[0] if proc.stdout.strip() else ""


def ahead_count(status_line: str) -> int:
    m = re.search(r"\[ahead (\d+)\]", status_line)
    return int(m.group(1)) if m else 0


def tag_on_remote(root: Path, tag: str) -> bool:
    proc = run(["git", "ls-remote", "--tags", "origin", tag], cwd=root, env=ssh_env())
    return proc.returncode == 0 and proc.stdout.strip() != ""


def refs_need_push(root: Path, branch: str, extra_refs: list[str]) -> tuple[bool, list[str]]:
    missing: list[str] = []
    status = git_status_short(root)
    if ahead_count(status) > 0:
        missing.append(branch)
    for ref in extra_refs:
        if not tag_on_remote(root, ref):
            missing.append(ref)
    return len(missing) > 0, missing


def test_ssh() -> tuple[bool, str]:
    proc = run(
        ["ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=15", "-T", "git@github.com"],
        cwd=Path.cwd(),
        timeout=SSH_TEST_TIMEOUT_SEC,
    )
    combined = (proc.stdout + proc.stderr).strip()
    # GitHub returns exit 1 with success message when auth works.
    ok = "successfully authenticated" in combined.lower() or "hi " in combined.lower()
    return ok, combined or f"exit {proc.returncode}"


def classify_exit(code: int) -> str:
    # Windows Git Bash segfault / access violation.
    if code in (-1073741819, 139, 3221225477):
        return "GIT_SEGFAULT"
    return "PUSH_FAILED"


def classify_stderr(stderr: str, stdout: str) -> str:
    text = f"{stderr}\n{stdout}".lower()
    if "permission denied (publickey)" in text or "could not read from remote" in text:
        return "SSH_AUTH"
    if "connection timed out" in text or "timed out" in text:
        return "SSH_NETWORK"
    if "non-fast-forward" in text or "failed to push some refs" in text:
        return "PUSH_REJECTED"
    if "fork:" in text or "resource temporarily unavailable" in text:
        return "FORK_EXHAUSTED"
    if "authentication failed" in text:
        return "SSH_AUTH"
    return "PUSH_FAILED"


def recommended_fix(failure_class: str, remote: str, ssh_ssh_url: str | None) -> str:
    fixes = {
        "HTTPS_REMOTE": (
            f"Run: git remote set-url origin {ssh_ssh_url or 'git@github.com:reactwoo/<repo>.git'}"
        ),
        "SSH_AUTH": (
            "SSH key not available to BatchMode. Run: ssh -T git@github.com "
            "(interactive). Start ssh-agent and ssh-add your key, then retry."
        ),
        "SSH_NETWORK": "Check internet/VPN/firewall. Retry: python scripts/git_push.py",
        "GIT_SEGFAULT": (
            "Windows Git Bash crash. Close hung git/bash terminals, open a fresh shell, "
            "retry once: python scripts/git_push.py"
        ),
        "GIT_HUNG": (
            "Push produced no completion within timeout. Kill stuck git processes, "
            "verify remote is SSH not HTTPS, then retry."
        ),
        "PUSH_REJECTED": (
            "Remote has commits you lack. Run: git fetch origin && git status -sb "
            "then pull/rebase before push."
        ),
        "FORK_EXHAUSTED": (
            "Too many bash/git children (Windows). Close extra terminals/agents, "
            "wait 10s, push one repo at a time."
        ),
        "NOTHING_TO_PUSH": "Branch already synced with origin — no push needed.",
        "PUSH_FAILED": "Read push_output below. Fix the underlying git/SSH error, then retry.",
    }
    return fixes.get(failure_class, fixes["PUSH_FAILED"])


def print_diagnostic(
    *,
    root: Path,
    remote: str,
    status: str,
    failure_class: str,
    exit_code: int | None,
    ssh_ok: bool | None,
    ssh_detail: str,
    push_stdout: str,
    push_stderr: str,
    auto_actions: list[str],
) -> None:
    print("=== GIT PUSH DIAGNOSTIC ===")
    print(f"repo: {repo_name(root)}")
    print(f"path: {root}")
    print(f"remote: {remote}")
    print(f"status: {status}")
    if ssh_ok is not None:
        print(f"ssh_test: {'OK' if ssh_ok else 'FAILED'}")
        if ssh_detail:
            print(f"ssh_detail: {ssh_detail[:500]}")
    print(f"failure_class: {failure_class}")
    if exit_code is not None:
        print(f"exit_code: {exit_code}")
    if auto_actions:
        print("auto_actions:")
        for action in auto_actions:
            print(f"  - {action}")
    print(f"recommended_fix: {recommended_fix(failure_class, remote, https_to_ssh(remote))}")
    if push_stdout.strip():
        print("push_output:")
        print(push_stdout.strip()[-2000:])
    if push_stderr.strip():
        print("push_stderr:")
        print(push_stderr.strip()[-2000:])
    print("=== END DIAGNOSTIC ===")


def main() -> int:
    parser = argparse.ArgumentParser(description="Push origin/main with diagnostics.")
    parser.add_argument("--repo", type=Path, default=Path.cwd(), help="Repo root")
    parser.add_argument("--branch", default="main", help="Branch to push (default: main)")
    parser.add_argument("--ref", action="append", default=[], help="Extra refs (e.g. tag v1.2.3)")
    parser.add_argument("--diagnose-only", action="store_true", help="Preflight only, no push")
    parser.add_argument(
        "--fix-https-remote",
        action=argparse.BooleanOptionalAction,
        default=True,
        help="Auto-convert HTTPS origin to SSH (default: on)",
    )
    args = parser.parse_args()

    root = args.repo.resolve()
    if not (root / ".git").exists():
        print(f"ERROR: not a git repo: {root}", file=sys.stderr)
        return 2

    auto_actions: list[str] = []
    remote = git_remote(root)
    status = git_status_short(root)
    ahead = ahead_count(status)

    if remote.startswith("https://github.com/"):
        ssh_url = https_to_ssh(remote)
        if args.fix_https_remote and ssh_url:
            proc = run(["git", "remote", "set-url", "origin", ssh_url], cwd=root)
            if proc.returncode == 0:
                auto_actions.append(f"Converted origin to SSH: {ssh_url}")
                remote = ssh_url
            else:
                print_diagnostic(
                    root=root,
                    remote=remote,
                    status=status,
                    failure_class="HTTPS_REMOTE",
                    exit_code=proc.returncode,
                    ssh_ok=None,
                    ssh_detail="",
                    push_stdout=proc.stdout,
                    push_stderr=proc.stderr,
                    auto_actions=auto_actions,
                )
                return 2
        else:
            print_diagnostic(
                root=root,
                remote=remote,
                status=status,
                failure_class="HTTPS_REMOTE",
                exit_code=None,
                ssh_ok=None,
                ssh_detail="",
                push_stdout="",
                push_stderr="HTTPS remotes hang on Windows Credential Manager.",
                auto_actions=auto_actions,
            )
            return 2

    ssh_ok, ssh_detail = test_ssh()
    if not ssh_ok:
        print_diagnostic(
            root=root,
            remote=remote,
            status=status,
            failure_class="SSH_AUTH",
            exit_code=None,
            ssh_ok=False,
            ssh_detail=ssh_detail,
            push_stdout="",
            push_stderr="",
            auto_actions=auto_actions,
        )
        return 2

    if ahead == 0 and not args.ref:
        print(f"OK: {repo_name(root)} — nothing to push ({status})")
        return 0

    need_push, missing_refs = refs_need_push(root, args.branch, args.ref)
    if not need_push:
        print(f"OK: {repo_name(root)} — branch and tag(s) already on origin ({status})")
        return 0

    if args.diagnose_only:
        print(f"OK (diagnose): {repo_name(root)} ahead {ahead}, SSH OK, remote {remote}")
        return 0

    push_cmd = ["git", "push", "origin", args.branch, *args.ref]
    try:
        proc = run(push_cmd, cwd=root, timeout=PUSH_TIMEOUT_SEC, env=ssh_env())
    except subprocess.TimeoutExpired:
        print_diagnostic(
            root=root,
            remote=remote,
            status=status,
            failure_class="GIT_HUNG",
            exit_code=None,
            ssh_ok=ssh_ok,
            ssh_detail=ssh_detail,
            push_stdout="",
            push_stderr=f"Timed out after {PUSH_TIMEOUT_SEC}s with no completion.",
            auto_actions=auto_actions,
        )
        return 1

    if proc.returncode == 0:
        fetch = run(["git", "fetch", "origin"], cwd=root, env=ssh_env())
        final_status = git_status_short(root)
        print(f"OK: pushed {repo_name(root)} ({args.branch})")
        print(final_status)
        if fetch.stderr:
            print(fetch.stderr.strip())
        return 0

    failure_class = classify_exit(proc.returncode)
    if failure_class == "PUSH_FAILED":
        failure_class = classify_stderr(proc.stderr, proc.stdout)

    print_diagnostic(
        root=root,
        remote=remote,
        status=status,
        failure_class=failure_class,
        exit_code=proc.returncode,
        ssh_ok=ssh_ok,
        ssh_detail=ssh_detail,
        push_stdout=proc.stdout,
        push_stderr=proc.stderr,
        auto_actions=auto_actions,
    )
    return 1


if __name__ == "__main__":
    sys.exit(main())
