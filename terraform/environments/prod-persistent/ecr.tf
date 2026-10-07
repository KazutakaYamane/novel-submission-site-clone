# laravel-nginx は Laravel タスクの nginx sidecar。Fargate は bind mount できないため
# 設定と public/ を焼き込んだイメージを別に持つ。

locals {
  ecr_repositories = ["laravel-app", "laravel-nginx", "nextjs"]
}

resource "aws_ecr_repository" "this" {
  for_each = toset(local.ecr_repositories)

  name = "${var.project}/${each.key}"

  # コミット SHA のタグで push し、同じタグは上書きしない。
  image_tag_mutability = "IMMUTABLE"

  image_scanning_configuration {
    scan_on_push = true
  }

  force_delete = true

  tags = { Name = "${var.project}-${var.environment}-${each.key}" }
}

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
          countNumber = 30
        }
        action = { type = "expire" }
      }
    ]
  })
}
