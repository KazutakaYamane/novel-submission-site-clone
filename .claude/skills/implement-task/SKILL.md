---
name: implement-task
description: Implement one task memo end to end — read it, open the GitHub issue, branch, implement test-first, then run code-review. Use only when the user names a task to implement (e.g. `/implement-task T-0019`).
argument-hint: <task id, e.g. T-0019> [code-review level]
disable-model-invocation: true
---

Implement the task named in `$ARGUMENTS` and stop after the code review. This skill only orders the steps; the rules themselves are in `AGENTS.md`, `backend/AGENTS.md`, `frontend/AGENTS.md`, and the `interviewer-docs` skill. Follow them.

## 1. Read the task

- Find the file in `docs/agent_memo/todo/tasks/` whose name starts with the given id (`T-0019`, or `19` padded to `T-0019`). If there is no match, or the file already has `issue:` set, stop and tell the user.
- Read the whole file, then everything in `spec_refs` (`docs/agent_memo/todo/mvp.md` sections, `docs/adr/`) and the AGENTS.md of each side the task touches.
- For each id in `depends_on` and `blocked_by`, check in the code that the work exists. Do not trust the other task file's `status`. If a dependency is missing, stop and report what is missing.
- If the task's 方針 or 受け入れ条件 conflicts with an ADR or a coding rule, stop and report it before creating anything (see "Sources of truth" in `AGENTS.md`).

## 2. Issue and branch

- Require a clean working tree; if it is dirty, stop. Then branch from the latest `master`: `git switch master && git pull --ff-only`.
- Load the `interviewer-docs` skill and write the issue from `.github/ISSUE_TEMPLATE/feature.md` (the body without the front matter). Turn the task's 受け入れ条件 into 事前条件 / 主シナリオ / 例外 / 事後条件, put tasks deferred elsewhere under 範囲外 with their ids, and put the design decisions from 方針 with linked ADRs under 設計メモ.
- Create it with `gh issue create --title "<title>" --body-file <scratchpad file>` and read back the issue number.
- In the task file's front matter, add `issue: <number>` and set `updated:` to today.
- `git switch -c <number>-<slug>`, where `<slug>` is the task file name without `T-NNNN-` and `.md`.

## 3. Implement test-first

Work through the acceptance criteria one at a time, following "Tests" in `AGENTS.md`:

1. Red: write the test, run only that test, and confirm it fails for the reason the criterion describes. An import, autoload, or setup error is not red; fix it and run again.
2. Green: the least code that passes.
3. Refactor, then rerun the affected suite.

Run commands the way the side's AGENTS.md says (inside the containers). As you go, append to the task's 作業ログ what you decided and why, and what you checked in bundled docs or the code. For criteria checked by hand, record the command and its output in 結果.

When the criteria are done, run every check the task's acceptance criteria list (lint, static analysis, type check, tests, build) and fix failures. Check off the criteria that now hold.

Do not commit or push; the user reviews the diff first.

## 4. Code review

Run the `code-review` skill on the working-tree diff, passing the level from `$ARGUMENTS` if one was given. Do not apply its findings yourself.

## 5. Report

- If the review has no findings and every criterion is checked, set `status: done` and move the task file to `docs/agent_memo/done/tasks/`. Otherwise leave it in `todo/` and say what remains.
- Tell the user: the issue URL, the branch, what changed per criterion, check results, review findings, and any `README.md` passages the change invalidated (the author rewrites them).
