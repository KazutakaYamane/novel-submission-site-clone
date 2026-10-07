# AGENTS.md

Novel submission platform clone built as a job-interview portfolio. Laravel JSON API (`backend/`) + Next.js UI (`frontend/`), Docker Compose locally, AWS ECS in prod via Terraform (`terraform/`).

## Sources of truth

- `docs/adr/`: architecture, infra, and tech-stack decisions. If a change conflicts with an ADR, propose an ADR update instead of silently diverging.
- When code changes invalidate `README.md` or `docs/adr/` text, update that text in the same change.
- `docs/agent_memo/` (gitignored): implementation specs. Delete each spec once code and tests replace it.

## Commands

PHP runs in the container: `docker compose exec app <cmd>`

- Test: `./vendor/bin/pest [--filter Name]`
- Static analysis: `./vendor/bin/phpstan analyse`
- Format: `./vendor/bin/pint` (`--test` to check only)

## Constraints

- Laravel returns JSON only. All UI rendering belongs to Next.js.
- Generate UUIDs in the app with `Str::uuid()`, not in the DB.


## Code comments

- Write a comment only for what the code cannot show: why, constraints, external requirements, non-obvious side effects.
- Do not restate the code, label sections with banners, narrate the change history, or leave TODOs without a concrete next step.
- Write comments in Japanese.
