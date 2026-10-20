# AGENTS.md

Novel submission platform clone built as a job-interview portfolio. Laravel JSON API (`backend/`) + Next.js UI (`frontend/`), Docker Compose locally, AWS ECS in prod via Terraform (`terraform/`).

## Sources of truth

- `docs/adr/`: the source of truth for decisions and why they were made. One decision per file, named `NNNN-<slug>.md` in the order decided; `docs/adr/README.md` lists each with its status. An ADR is not edited to follow the code; when a change conflicts with an ADR's decision or its premises, stop and propose a new ADR that supersedes it. The old ADR stays, marked `superseded by`.
- `AGENTS.md`, `backend/AGENTS.md`, `frontend/AGENTS.md`, and mechanical checks (`arch()` tests, PHPStan, ESLint): the source of truth for coding rules. If a rule contradicts an ADR, report the contradiction to the user instead of changing either side.
- When code changes invalidate `README.md` text, list the affected passages to the user in the same change. The author rewrites them.
- `docs/agent_memo/` (gitignored): implementation specs. Keep specs for work not yet implemented in `docs/agent_memo/todo/`, and move each spec to `docs/agent_memo/done/` once its implementation is finished.
- `docs/agent_memo/todo/tasks/`: task memos for unfinished work. When a task is finished, set `status: done` and move the file to `docs/agent_memo/done/tasks/` in the same step.
- Starting a task: create a GitHub issue with `gh issue create` from `.github/ISSUE_TEMPLATE/feature.md`, record the issue number in the task file's front matter as `issue:`, and check out a branch named `<issue number>-<slug>` (the task file name without the `T-NNNN-` prefix, e.g. `12-auth-register`). The user's instruction to start a task authorizes creating that issue.
- Task scope: the user reads and understands all code, so implement exactly one task per request (the one the user names) and stop. Do not treat other task files' contents (results, work logs, `status`) as the current state of the code; verify against the repo.

## Design principles

The project is in early development and has no backward-compatibility obligations. Always build the optimal design and structure.

- Do not add a workaround, a compatibility shim, or a `moved`/migration step whose only purpose is to preserve the current state of a design that is not optimal. Change the design itself and rebuild what depends on it.
- Before a change that breaks existing code, data, or infrastructure, investigate the blast radius (callers, state, running resources, docs) and show the user the optimal design, what it breaks, and the cost of rebuilding. Then proceed once the user agrees.
- If the optimal design is not chosen, propose recording it as an accepted trade-off in an ADR instead of presenting a workaround as the design.

## Where coding rules live

- Before changing code under `backend/`, read `backend/AGENTS.md` (layers, per-layer rules, commands, test layout).
- Before changing code under `frontend/`, read `frontend/AGENTS.md` (boundaries, caching, data fetching, Server Actions, auth, commands).
- `docs/adr/` records why decisions were made. do not put coding rules there.
- Prefer enforcing a rule mechanically (Pest `arch()` tests, PHPStan, ESLint) over describing it in prose.

## Constraints

- Laravel returns JSON only. All UI rendering belongs to Next.js.
- Generate UUIDs in the app with `Str::uuid()`, not in the DB.

## Tests

- A test double that implements an interface implements every method with the same behavior as the real implementation, backed by the double's in-memory state (e.g. a fake repository's `findById` searches the rows that its `create` stored). Do not fill a method the test does not call with a fixed return value such as `return null`.

## Code comments

- Write a comment only for what the code cannot show: why, constraints, external requirements, non-obvious side effects.
- Do not restate the code, label sections with banners, narrate the change history, or leave TODOs without a concrete next step.
- Write comments in Japanese.
