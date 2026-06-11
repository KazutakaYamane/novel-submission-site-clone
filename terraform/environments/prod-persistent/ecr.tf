# ---------------------------------------------------------------------------
# ECR リポジトリ
# ---------------------------------------------------------------------------
# 3 リポジトリ構成:
#   - laravel-app  : php-fpm(Laravel 本体)。docker/app/Dockerfile target: prod
#   - laravel-nginx: Laravel タスクの nginx sidecar。Fargate は bind mount 不可のため
#                    設定 + public/ を焼き込んだカスタムイメージが必要
#   - nextjs       : Next.js standalone(方式A の独立サービス)
#
# prod 本体ではなくこの root に置く理由: リポジトリ(とイメージ)はサービスの
# destroy/recreate サイクルから独立した置き場。prod を destroy しても push 済み
# イメージを失わない。

locals {
  ecr_repositories = ["laravel-app", "laravel-nginx", "nextjs"]
}

resource "aws_ecr_repository" "this" {
  for_each = toset(local.ecr_repositories)

  name = "${var.project}/${each.key}"

  # フェーズ1 は手動 push(latest 等のタグ運用)のため MUTABLE。
  # フェーズ2 の CI/CD で sha タグ + IMMUTABLE 化を検討する。
  image_tag_mutability = "MUTABLE"

  image_scanning_configuration {
    scan_on_push = true
  }

  # この root 自体を畳むとき(プロジェクト完全撤収時)用
  force_delete = true

  tags = { Name = "${var.project}-${var.environment}-${each.key}" }
}

# 古いイメージの自動削除(ストレージ課金を抑える)。直近 10 イメージのみ保持。
resource "aws_ecr_lifecycle_policy" "this" {
  for_each = aws_ecr_repository.this

  repository = each.value.name

  policy = jsonencode({
    rules = [
      {
        rulePriority = 1
        description  = "Keep last 10 images"
        selection = {
          tagStatus   = "any"
          countType   = "imageCountMoreThan"
          countNumber = 10
        }
        action = { type = "expire" }
      }
    ]
  })
}
