# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A novel submission site clone (novel publishing platform) built as a portfolio project. The infrastructure and Docker setup are complete; the Laravel application itself is scaffolded inside Docker containers.

## Architecture & Design Decisions

For architecture decisions, infrastructure design, and tech stack rationale,
refer to `docs/portfolio-project-knowledge.md` before making any suggestions.

## Development Setup

The host machine needs only Docker — no PHP, Composer, or Node installation required.

```bash
# First-time setup
cp .env.example .env

# Backend (Laravel JSON API) — scaffolds into app/
docker compose run --rm app composer create-project laravel/laravel . "^13.0"
docker compose run --rm app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate

# Frontend (Next.js + React + TypeScript) — scaffolds into frontend/
#   Per-page SSG/SSR/ISR; runs as its own ECS service in prod (method A). See ADR-FE / ADR-INFRA.
#   The frontend container's WORKDIR is /app, bind-mounted to ./frontend.
#   next.config.ts: rewrites '/api' → Nginx (browser/client fetch, same-origin, no CORS).
#   Server-side fetch (SSR/ISR) targets INTERNAL_API_URL: http://nginx:80 locally,
#   http://api:80 (Service Connect) in prod.
mkdir -p frontend
docker compose --profile frontend run --rm web sh -c "npx create-next-app@latest . --ts && npm install"

# Frontend HMR (separate terminal) — Next.js dev server (port 3000)
docker compose --profile frontend up web
```

## Common Commands

All PHP/Laravel commands run inside the container:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
docker compose exec app ./vendor/bin/pest           # run all tests
docker compose exec app ./vendor/bin/pest --filter TestName  # single test
docker compose exec app ./vendor/bin/phpstan analyse # static analysis
docker compose exec app ./vendor/bin/pint           # format code
docker compose exec app ./vendor/bin/pint --test    # check formatting only
docker compose exec app sh                          # shell access

docker compose down        # stop services
docker compose down -v     # stop + delete all data volumes
```

## Architecture

### Service Layout (compose.yaml)

Seven services, all defined in a single `compose.yaml`:

| Service | Port | Role |
|---------|------|------|
| nginx | 8080 | Reverse proxy in front of Laravel JSON API |
| app | 9000 (internal) | PHP-FPM / Laravel (JSON API only — UI rendering is owned by the Next.js frontend) |
| db | 3306 | MySQL 8.0 |
| redis | 6379 | Sessions, cache, queues |
| mailpit | 8025 (UI), 1025 (SMTP) | Email testing |
| minio | 9000 (API), 9001 (console) | Local S3-compatible storage |
| web | 3000 | Next.js dev server (HMR) for `frontend/` (profile: frontend). Mirrors the prod two-service naming (web=Next.js / api=Laravel) |

### Docker Multi-Stage Design (`docker/app/Dockerfile`)

- **base**: PHP 8.4-fpm-alpine, extensions, Composer, opcache+JIT
- **dev**: Adds Xdebug; does NOT require `app/` to exist at build time (bind-mounted at runtime, enables `composer create-project` inside container)
- **prod**: Copies `app/` source, resolves dependencies, runs `artisan optimize`

The `dev` stage intentionally avoids copying `app/` so the image can be built before Laravel is scaffolded.

### Nginx → PHP-FPM

Nginx (`docker/nginx/default.conf`) proxies PHP requests to `app:9000` (FastCGI). The upstream name `app` matches the Compose service name and also works as `localhost` in ECS (sidecar pattern). The health endpoint `GET /health` returns 200 unconditionally (ALB health check compatible).

### Frontend

Next.js (React + TypeScript) lives in `frontend/`, fully independent from the Laravel project in `app/`. Pages choose per-page rendering (SSG/SSR/ISR): work list/detail/chapter pages use ISR/SSG for SEO + first-paint; authed dashboards and search use SSR/CSR. It runs in a separate dev container (`docker/node/Dockerfile`, WORKDIR `/app` bind-mounted to `./frontend`), gated behind the `frontend` profile so it doesn't start with `docker compose up -d` by default. Rationale: `docs/adr/ADR-FE` (§10.2) and `docs/adr/ADR-INFRA.md`.

In production, Next.js runs as its **own ECS service** (method A), behind the same ALB as Laravel with path routing (`default` → Next.js TG, `/api/*` → Laravel TG); static assets (`/_next/static/*`) are long-cached at CloudFront. The frontend is built into a Docker image (ARM64, `output: 'standalone'`, `sharp` resolved for ARM64) and deployed to ECS — it is **not** a static S3 upload.

Two API ingress paths to Laravel:
- **North-South** (browser/client fetch, mutations): CloudFront → ALB → Laravel `/api/*`.
- **East-West** (Next.js SSR/ISR server-side fetch): ECS **Service Connect** (`http://api:80`), kept inside the VPC (consistent with the NAT-less design).

ISR uses a custom `cacheHandler` backed by **ElastiCache** (shared across tasks; the default `.next/cache` is per-task/ephemeral on Fargate). **Critical:** CloudFront must not cache personalized SSR responses — get the cache-behavior split right or you risk serving one user's logged-in state to others.

Local dev: `next.config.ts` `rewrites` send `/api` → `http://nginx:80` (browser, same-origin, no CORS); server-side fetch uses `INTERNAL_API_URL` (`http://nginx:80` locally, `http://api:80` via Service Connect in prod).

### Database

MySQL 8.0 with `utf8mb4` / `utf8mb4_unicode_ci` configured at server level. Authentication plugin is `caching_sha2_password` (MySQL 8 default; pdo_mysql on PHP 8.4 supports it natively). Full-text search is planned via InnoDB FULLTEXT indexes with the `ngram` parser (Japanese tokenization built into MySQL 8), or by introducing a dedicated search engine (Meilisearch / OpenSearch) at the application layer. UUIDs are generated application-side via `Str::uuid()` rather than in the DB.

The `docker/db/init/` directory is mounted to `/docker-entrypoint-initdb.d` for any future MySQL-specific initialization SQL (additional DBs, users, etc.).

## Key Configuration Files

- `docker/app/php.ini` — shared PHP settings (timezone: Asia/Tokyo, session, uploads)
- `docker/app/php.dev.ini` — dev overrides (`display_errors: On`, opcache revalidation)
- `docker/app/php.prod.ini` — prod overrides (`display_errors: Off`)
- `docker/app/www.conf` — php-fpm pool (dynamic, 4–8 spare workers, 20 max children)
- `docker/nginx/default.conf` — virtual host for the Laravel JSON API, FastCGI timeout 60s

## Infrastructure (Terraform)

- `terraform/bootstrap/` — **applied**. Local state. Creates only the S3 bucket for remote tfstate.
- `terraform/environments/prod/` — **not applied** (no resources in AWS yet). Remote state on S3, S3-native lock. Wires `network` + `secrets` modules; `database`/`ecs-service`/`cloudfront` are TODO.
- `terraform/modules/` — `network` (VPC/subnet/SG, no NAT) and `secrets` (Secrets Manager) implemented.

So the AWS account currently runs **no billable resources** beyond the (empty) tfstate bucket.

## Planned but Not Yet Implemented

Per `portfolio-project-knowledge.md` (frontend = Next.js on ECS, "method A"; see `docs/adr/ADR-INFRA.md`):
- prod modules: `database` (RDS MySQL), `cache` (ElastiCache — required for ISR shared cache + Laravel session/cache/queue), `ecs-service` (ALB + 2 Target Groups + **two ECS services: Laravel & Next.js** + Auto Scaling + **Service Connect** for East-West), `cloudfront` (CloudFront + ACM, behaviors: `default`→Next.js / `/api/*`→Laravel / `/_next/static`→long-cache). Plus a Cloud Map HTTP namespace and split ECS SGs (Next.js / Laravel) with an East-West ingress rule.
- `.github/workflows/` — CI/CD (GitHub Actions with OIDC; ARM64 builds; **two image pipelines** — Laravel and Next.js — both build→ECR→ECS, plus static-asset S3 sync + CloudFront invalidation)
- `docs/adr/` — ADR-INFRA written; ADR-TF / ADR-BE / ADR-FE still to be authored as files (ADR-FE confirmed text lives in `portfolio-project-knowledge.md` §10.2)

> Decision history note: the frontend was SPA (Vite+React) through session 6; session 7 (2026-06-09) switched to Next.js on ECS. ADRs and `portfolio-project-knowledge.md` reflect the new direction.
