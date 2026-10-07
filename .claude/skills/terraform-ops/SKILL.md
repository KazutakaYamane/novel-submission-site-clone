---
name: terraform-ops
description: Terraform root module layout and the prod apply/destroy cost-cycling workflow for this repo's infrastructure. Use when applying, destroying, or reasoning about the cost of the prod environment, or when working out which root module owns a given resource. Also use when building GitHub Actions CI/CD (OIDC roles, ECR/ECS deploy).
---

## Infrastructure (Terraform)

Three root modules, each with its own state:

- `terraform/bootstrap/` — Local state. Creates only the S3 bucket for remote tfstate.
- `terraform/environments/prod-persistent/` — Remote state (`prod-persistent/terraform.tfstate`). Holds resources that must survive prod destroy/recreate cycles: the Route 53 hosted zone (destroying it changes the NS set, forcing re-delegation from the parent `kyyk517.com` account) the 3 ECR repositories (`laravel-app` / `laravel-nginx` / `nextjs` — images would be lost), and the GitHub Actions OIDC provider with two IAM roles (`github_deploy` / `github_plan`, `github-oidc.tf`). **Never destroy this root** during normal operation (~$0.50/month).
- `terraform/environments/prod/` — Applied and destroyed repeatedly (cost cycling, see below), so it may or may not exist at any moment. Check with `aws ecs list-clusters` before assuming either state. Defines the full walking-skeleton stack: VPC, RDS, ElastiCache, ALB+ACM, ECS cluster + 2 services, CloudFront, Secrets Manager. Remote state on S3, S3-native lock. References the zone and ECR via `data` sources (`data.tf`) — requires prod-persistent to be applied first.
- `terraform/modules/` — `network`, `secrets`, `cache`, `database`, `ecs-service`, `cloudfront` all implemented.

**Cost operation**: prod is designed for apply/destroy cycling (RDS `skip_final_snapshot`, secrets `recovery_window=0`). Work session: `apply` prod (~30–40 min, CloudFront is the long pole) → work → `destroy` prod (~20–30 min). Destroying prod loses DB data (re-run migrations/seeders) but keeps the zone delegation and pushed images. Full-running cost ≈ $50/month; destroyed ≈ $0.50/month.

## CI/CD

Implemented in `.github/workflows/`:

- `deploy.yml` (push to `master`, `workflow_dispatch`): ARM64 build on `ubuntu-24.04-arm` → ECR (`:<sha>` only, repositories are IMMUTABLE; existing tags are not rebuilt) → skip deploy if the ECS cluster is not ACTIVE → api (register a task definition revision from the family's latest revision with the SHA image, migration via one-off task, `update-service`) → web (S3 sync, CloudFront invalidation, register revision, `update-service`) → record the deployed SHAs in SSM (`/novel-submission-site-clone/prod/image-tag/{laravel,nextjs}`). Assumes `github_deploy`, which only trusts `ref:refs/heads/master`; never use `environment:` in this workflow. The OIDC `sub` claim embeds numeric IDs (`repo:<owner>@<owner_id>/<repo>@<repo_id>:...`), so the trust policies in `github-oidc.tf` match on `github_owner_id` / `github_repository_id`; a mismatch shows up as `Not authorized to perform sts:AssumeRoleWithWebIdentity`, and the real `sub` is visible in CloudTrail (`AssumeRoleWithWebIdentity`).
- `prod` reads those SSM parameters via `data.aws_ssm_parameter` to pick the image tags, so `plan`, `apply` and `destroy` all fail until the workflow has run once. Run it before the first `apply` of `prod`. The parameters are created by CI, not Terraform.
- `terraform-plan.yml` (pull_request): `terraform plan -lock=false` for `prod` and `prod-persistent` with `github_plan`. `terraform apply` is never run from GitHub Actions.

Design rationale is in `docs/adr/ADR-INFRA.md` (CI/CD section).
