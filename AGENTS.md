# AGENTS.md

Novel submission platform clone built as a job-interview portfolio. Laravel JSON API (`backend/`) + Next.js UI (`frontend/`), Docker Compose locally, AWS ECS in prod via Terraform (`terraform/`).

## Sources of truth

- `docs/adr/`: architecture, infra, and tech-stack decisions. If a change conflicts with an ADR, propose an ADR update instead of silently diverging.
- When code changes invalidate `README.md` or `docs/adr/` text, update that text in the same change.
- `docs/agent_memo/` (gitignored): implementation specs. Keep specs for work not yet implemented in `docs/agent_memo/todo/`, and move each spec to `docs/agent_memo/done/` once its implementation is finished.
- `docs/agent_memo/todo/tasks/`: task memos for unfinished work. When a task is finished, set `status: done` and move the file to `docs/agent_memo/done/tasks/` in the same step.
- Starting a task: create a GitHub issue with `gh issue create` from `.github/ISSUE_TEMPLATE/feature.md`, record the issue number in the task file's front matter as `issue:`, and check out a branch named `<issue number>-<slug>` (the task file name without the `T-NNNN-` prefix, e.g. `12-auth-register`). The user's instruction to start a task authorizes creating that issue.
- Task scope: the user reads and understands all code, so implement exactly one task per request (the one the user names) and stop. Do not treat other task files' contents (results, work logs, `status`) as the current state of the code; verify against the repo.

## Design principles

The project is in early development and has no backward-compatibility obligations. Always build the optimal design and structure.

- Do not add a workaround, a compatibility shim, or a `moved`/migration step whose only purpose is to preserve the current state of a design that is not optimal. Change the design itself and rebuild what depends on it.
- Before a change that breaks existing code, data, or infrastructure, investigate the blast radius (callers, state, running resources, docs) and show the user the optimal design, what it breaks, and the cost of rebuilding. Then proceed once the user agrees.
- If the optimal design is not chosen, record it as an accepted trade-off in the ADR instead of presenting a workaround as the design.

## Commands

PHP runs in the container: `docker compose exec app <cmd>`

- Test: `./vendor/bin/pest [--filter Name]`
- Static analysis: `./vendor/bin/phpstan analyse`
- Format: `./vendor/bin/pint` (`--test` to check only)

## Constraints

- Laravel returns JSON only. All UI rendering belongs to Next.js.
- Generate UUIDs in the app with `Str::uuid()`, not in the DB.
- Backend code follows the layers in `docs/adr/ADR-BACKEND.md`: single-action (`__invoke`) controllers and use cases, repository interfaces in `app/Domain`, and Eloquent only in `app/Infrastructure`.


## Code comments

- Write a comment only for what the code cannot show: why, constraints, external requirements, non-obvious side effects.
- Do not restate the code, label sections with banners, narrate the change history, or leave TODOs without a concrete next step.
- Write comments in Japanese.
