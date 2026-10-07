---
name: terraform-ops
description: Terraform root module layout and the prod apply/destroy cost-cycling workflow for this repo's infrastructure. Use when applying, destroying, or reasoning about the cost of the prod environment, or when working out which root module owns a given resource. Also use when building GitHub Actions CI/CD (OIDC roles, ECR/ECS deploy).
---

## Infrastructure (Terraform)

Three root modules, each with its own state:

- `terraform/bootstrap/` — **applied**. Local state. Creates only the S3 bucket for remote tfstate.
- `terraform/environments/prod-persistent/` — **applied**. Remote state (`prod-persistent/terraform.tfstate`). Holds resources that must survive prod destroy/recreate cycles: the Route 53 hosted zone (destroying it changes the NS set, forcing re-delegation from the parent `kyyk517.com` account) the 3 ECR repositories (`laravel-app` / `laravel-nginx` / `nextjs` — images would be lost), and the GitHub Actions OIDC provider with two IAM roles (`github_deploy` / `github_plan`, `github-oidc.tf`). **Never destroy this root** during normal operation (~$0.50/month).
- `terraform/environments/prod/` — **currently destroyed** (verified 2026-10-06: empty state, no ECS cluster, CloudFront, RDS, or ALB). Defines the full walking-skeleton stack: VPC, RDS, ElastiCache, ALB+ACM, ECS cluster + 2 services, CloudFront, Secrets Manager. Remote state on S3, S3-native lock. References the zone and ECR via `data` sources (`data.tf`) — requires prod-persistent to be applied first.
- `terraform/modules/` — `network`, `secrets`, `cache`, `database`, `ecs-service`, `cloudfront` all implemented.

**Cost operation**: prod is designed for apply/destroy cycling (RDS `skip_final_snapshot`, secrets `recovery_window=0`). Work session: `apply` prod (~30–40 min, CloudFront is the long pole) → work → `destroy` prod (~20–30 min). Destroying prod loses DB data (re-run migrations/seeders) but keeps the zone delegation and pushed images. Full-running cost ≈ $50/month; destroyed ≈ $0.50/month.

## CI/CD (planned, not implemented)

`.github/workflows/` does not exist yet. The OIDC provider and the `github_deploy` / `github_plan` IAM roles already exist in `terraform/environments/prod-persistent/github-oidc.tf`. Planned pipeline: ARM64 builds, two image pipelines (Laravel, Next.js) to ECR → ECS, static assets to S3 + CloudFront invalidation.
