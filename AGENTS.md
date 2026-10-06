# AGENTS.md

Novel submission platform clone built as a job-interview portfolio. Laravel JSON API (`backend/`) + Next.js UI (`frontend/`), Docker Compose locally, AWS ECS in prod via Terraform (`terraform/`).

## Sources of truth

- `docs/adr/`: architecture, infra, and tech-stack decisions. Read before proposing design changes. If a change conflicts with an ADR, propose an ADR update instead of silently diverging.
- `docs/agent_memo/` (gitignored): implementation specs (DB schema, API contract, state machine, validation, domain model, ISR contract). Delete each spec once code and tests replace it.
- `setup` skill: first-time scaffold of `backend/` and `frontend/`, frontend HMR startup.
- `terraform-ops` skill: Terraform root layout, prod apply/destroy cost-cycling.
- `frontend/CLAUDE.md`: rendering strategy, CloudFront/ECS routing, ISR cache, local API routing.

## Commands

PHP runs in the container: `docker compose exec app <cmd>`

- Test: `./vendor/bin/pest [--filter Name]`
- Static analysis: `./vendor/bin/phpstan analyse`
- Format: `./vendor/bin/pint` (`--test` to check only)
- `php artisan migrate` / `php artisan tinker`
- `docker compose down` (`-v` also deletes DB/Redis/MinIO volumes)

## Constraints not obvious from the code

- Laravel returns JSON only. All UI rendering belongs to Next.js.
- The `dev` stage of `docker/app/Dockerfile` must not COPY `backend/`. It is bind-mounted so the image builds before Laravel is scaffolded.
- Nginx upstream `app:9000` must match the Compose service name; the same name resolves as localhost in the ECS sidecar. `GET /health` always returns 200 for the ALB.
- Compose service `web` = Next.js, matching prod naming (web = Next.js / api = Laravel).
- MySQL 8.0, `utf8mb4_unicode_ci` at server level. Generate UUIDs in the app with `Str::uuid()`, not in the DB.
- Full-text search is undecided: InnoDB FULLTEXT + `ngram`, or Meilisearch/OpenSearch.

## Not yet implemented

- アプリ本体: `backend/`(Laravel)と`frontend/`(Next.js)は動作確認用の最小構成のみ。`GET /api/health`(DB/Redis疎通)、SSRの`/`、ISR確認用の`/isr`だけがある
- `.github/workflows/`: GitHub Actions workflows. The OIDC provider and the `github_deploy` / `github_plan` IAM roles already exist in `terraform/environments/prod-persistent/github-oidc.tf`. ARM64 builds, two image pipelines (Laravel, Next.js) to ECR → ECS, static assets to S3 + CloudFront invalidation
- ADR-TF, ADR-BE, ADR-FE (ADR-FE draft is in `portfolio-project-knowledge.md` §10.2)

## Writing style (all prose: docs, READMEs, ADRs, code comments, commit messages)

Rewrite any sentence that breaks a rule.
1. Start with the main statement. Do not open with an unneeded contrast, negation, or hedge. A prohibition that is itself the point ("must not COPY `backend/`") is fine.
   - NG: 「単にコンテナ化するだけではなく、〜」「必ずしも最適とは言えないが、〜」
   - OK: 「LaravelとNext.jsを別のECSサービスとしてデプロイする。」
2. Name the concrete action and target. No abstract words or metaphors that leave what is done unclear.
   - NG: 「スケーラブルな基盤を実現する」「データの流れをシームレスにつなぐ」
   - OK: 「ALBのターゲットグループを2つに分け、`/api/*`をLaravelに転送する。」
3. Do not make text look more impressive than its content with fragments, parallel phrasing, odd commas, or forced nominalization.
   - NG: 「速さ。そして、堅牢さ。」「コストは最小に、可用性は最大に。」「キャッシュの活用による高速化の実現」
   - OK: 「ISRのキャッシュをElastiCacheに置き、Next.jsの全タスクで共有する。」
4. Do not write text just to fill a heading, template section, or list. If there is nothing specific to this project to say, delete the heading or item, or write 「なし」. Do not pad lists to a set count.
   - NG: 「今後の展望：さらなる改善を継続していく」「メリット：保守性・拡張性・可読性の向上」
   - OK: 「デメリット：NAT Gatewayを置かないため、ECSタスクをpublic subnetに置きPublic IPを持たせる必要がある。」
5. Do not literally translate technical terms into Japanese words that Japanese engineers do not use. Keep the original term (identifier, API name, or katakana) that appears in Japanese official docs and tech articles, and add a short explanation on first use if needed.
   - NG: 「再検証を依頼する」(revalidate)、「起源」(origin)、「配布」(distribution)
   - OK: 「`revalidateTag`(指定タグのキャッシュ破棄)を依頼する」「オリジン」「ディストリビューション」

## Code comments

- Write a comment only for what the code cannot show: why, constraints, external requirements, non-obvious side effects.
- Do not restate the code, label sections with banners, narrate the change history ("fixed", "updated to"), or leave TODOs without a concrete next step.
- Write comments in Japanese.

## Interviewer-facing docs (`README.md`, `docs/adr/`)

Readers are interviewers seeing the project for the first time. Write in Japanese.
- README order: what the app does → architecture diagram → tech stack with a one-line reason each, linking the ADR → local setup commands → implemented vs planned.
- README includes a section on AI usage: that Claude Code is used, and which parts the author decided and verified (design decisions, ADRs, tests).
- ADR sections: Context / Decision / Alternatives considered (why rejected) / Consequences (including accepted trade-offs).
- Back claims with concrete facts: service names, numbers, config values, file paths. Mark planned work as planned; never describe it as done.
- When code changes invalidate README or ADR text, update that text in the same change.
