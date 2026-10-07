# GitHub Actions が短命トークンで引き受ける IAM ロール。
#   - github-deploy: master への push のみ。ECR push / ECS デプロイ / マイグレーション /
#     静的アセット S3 sync / CloudFront invalidation。
#   - github-plan  : pull_request のみ。terraform plan 用の読み取り専用。
# prod の destroy 中も ECR への push は通したいので、prod ではなくこの root に置く。

data "aws_caller_identity" "current" {}

locals {
  # prod 側のリソースは作り直しで ID が変わるため、決定的な名前で縛る
  prod_prefix          = "${var.project}-${var.environment}"
  ecs_cluster_arn      = "arn:aws:ecs:${var.region}:${data.aws_caller_identity.current.account_id}:cluster/${local.prod_prefix}"
  static_assets_bucket = "${local.prod_prefix}-static-assets"

  # sub クレームは名前に数値 ID を付けた形式で発行される
  github_owner      = split("/", var.github_repository)[0]
  github_repo       = split("/", var.github_repository)[1]
  github_sub_prefix = "repo:${local.github_owner}@${var.github_owner_id}/${local.github_repo}@${var.github_repository_id}"
}

resource "aws_iam_openid_connect_provider" "github" {
  url            = "https://token.actions.githubusercontent.com"
  client_id_list = ["sts.amazonaws.com"]
  # AWS は 2023 年以降 GitHub OIDC の証明書をルート CA で検証するため thumbprint は
  # 実質使われないが、API 仕様上必須のため既知の値を置く
  thumbprint_list = ["6938fd4d98bab03faadb97b34396831e3780aea1"]
}

# ---------------------------------------------------------------------------
# deploy ロール(master push のみ)
# ---------------------------------------------------------------------------

data "aws_iam_policy_document" "github_deploy_trust" {
  statement {
    actions = ["sts:AssumeRoleWithWebIdentity"]

    principals {
      type        = "Federated"
      identifiers = [aws_iam_openid_connect_provider.github.arn]
    }

    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:aud"
      values   = ["sts.amazonaws.com"]
    }

    # master ブランチへの push でのみ引き受け可(PR・フォークからは不可)
    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:sub"
      values   = ["${local.github_sub_prefix}:ref:refs/heads/master"]
    }
  }
}

resource "aws_iam_role" "github_deploy" {
  name               = "${var.project}-github-deploy"
  assume_role_policy = data.aws_iam_policy_document.github_deploy_trust.json
}

data "aws_iam_policy_document" "github_deploy" {
  # ECR ログイン(API 仕様上リソース指定不可)
  statement {
    sid       = "EcrAuth"
    actions   = ["ecr:GetAuthorizationToken"]
    resources = ["*"]
  }

  # 3 リポジトリへの push(キャッシュ利用の pull 系も含む)
  statement {
    sid = "EcrPush"
    actions = [
      "ecr:BatchCheckLayerAvailability",
      "ecr:GetDownloadUrlForLayer",
      "ecr:BatchGetImage",
      "ecr:InitiateLayerUpload",
      "ecr:UploadLayerPart",
      "ecr:CompleteLayerUpload",
      "ecr:PutImage",
    ]
    resources = [for repo in aws_ecr_repository.this : repo.arn]
  }

  # ECS デプロイ(web / api の 2 サービス)
  statement {
    sid = "EcsDeploy"
    actions = [
      "ecs:UpdateService",
      "ecs:DescribeServices",
    ]
    resources = ["arn:aws:ecs:${var.region}:${data.aws_caller_identity.current.account_id}:service/${local.prod_prefix}/${local.prod_prefix}-*"]
  }

  # マイグレーションの one-off task(api のタスク定義のみ・クラスタを限定)
  statement {
    sid       = "EcsRunMigrationTask"
    actions   = ["ecs:RunTask"]
    resources = ["arn:aws:ecs:${var.region}:${data.aws_caller_identity.current.account_id}:task-definition/${local.prod_prefix}-api:*"]

    condition {
      test     = "ArnEquals"
      variable = "ecs:cluster"
      values   = [local.ecs_cluster_arn]
    }
  }

  statement {
    sid       = "EcsDescribeTasks"
    actions   = ["ecs:DescribeTasks"]
    resources = ["arn:aws:ecs:${var.region}:${data.aws_caller_identity.current.account_id}:task/${local.prod_prefix}/*"]
  }

  # クラスタ存在チェック(prod destroy 中はデプロイをスキップするガード用)
  statement {
    sid       = "EcsDescribeClusters"
    actions   = ["ecs:DescribeClusters"]
    resources = [local.ecs_cluster_arn]
  }

  # DescribeTaskDefinition / RegisterTaskDefinition はリソースレベル制限非対応
  statement {
    sid       = "EcsTaskDefinition"
    actions   = ["ecs:DescribeTaskDefinition", "ecs:RegisterTaskDefinition"]
    resources = ["*"]
  }

  # 既存のタスク定義のタグを引き継いで登録するために必要
  statement {
    sid       = "EcsTagTaskDefinition"
    actions   = ["ecs:TagResource"]
    resources = ["arn:aws:ecs:${var.region}:${data.aws_caller_identity.current.account_id}:task-definition/${local.prod_prefix}-*:*"]
  }

  # 同じコミットの再実行で、push 済みのイメージのビルドを省略するための存在確認
  statement {
    sid       = "EcrDescribeImages"
    actions   = ["ecr:DescribeImages"]
    resources = [for repo in aws_ecr_repository.this : repo.arn]
  }

  # デプロイに成功したイメージの SHA の記録(prod の Terraform が起動時に読む)
  statement {
    sid       = "SsmImageTag"
    actions   = ["ssm:PutParameter"]
    resources = ["arn:aws:ssm:${var.region}:${data.aws_caller_identity.current.account_id}:parameter/${var.project}/${var.environment}/image-tag/*"]
  }

  # one-off task にタスクロール/実行ロールを渡す(ECS タスクへの受け渡しに限定)
  statement {
    sid       = "PassTaskRoles"
    actions   = ["iam:PassRole"]
    resources = ["arn:aws:iam::${data.aws_caller_identity.current.account_id}:role/${local.prod_prefix}-*"]

    condition {
      test     = "StringEquals"
      variable = "iam:PassedToService"
      values   = ["ecs-tasks.amazonaws.com"]
    }
  }

  # Next.js 静的アセットの S3 sync(バケットは prod 側だが名前は決定的)
  statement {
    sid       = "StaticAssetsList"
    actions   = ["s3:ListBucket"]
    resources = ["arn:aws:s3:::${local.static_assets_bucket}"]
  }

  statement {
    sid = "StaticAssetsWrite"
    actions = [
      "s3:GetObject",
      "s3:PutObject",
      "s3:DeleteObject",
    ]
    resources = ["arn:aws:s3:::${local.static_assets_bucket}/*"]
  }

  # CloudFront invalidation。distribution ID は prod の destroy/recreate で変わるため
  # ID では縛れない(エイリアス検索 + invalidation)
  statement {
    sid = "CloudFront"
    actions = [
      "cloudfront:CreateInvalidation",
      "cloudfront:ListDistributions",
    ]
    resources = ["*"]
  }
}

resource "aws_iam_role_policy" "github_deploy" {
  name   = "deploy"
  role   = aws_iam_role.github_deploy.id
  policy = data.aws_iam_policy_document.github_deploy.json
}

# ---------------------------------------------------------------------------
# plan ロール(pull_request のみ・読み取り専用)
# ---------------------------------------------------------------------------

data "aws_iam_policy_document" "github_plan_trust" {
  statement {
    actions = ["sts:AssumeRoleWithWebIdentity"]

    principals {
      type        = "Federated"
      identifiers = [aws_iam_openid_connect_provider.github.arn]
    }

    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:aud"
      values   = ["sts.amazonaws.com"]
    }

    # 自リポジトリの pull_request イベントでのみ引き受け可
    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:sub"
      values   = ["${local.github_sub_prefix}:pull_request"]
    }
  }
}

resource "aws_iam_role" "github_plan" {
  name               = "${var.project}-github-plan"
  assume_role_policy = data.aws_iam_policy_document.github_plan_trust.json
}

# terraform plan の refresh は広範な Describe/List/Get を要するため AWS 管理の
# ReadOnlyAccess を使う(tfstate バケットの読み取りもこれに含まれる)。
# plan は -lock=false で実行する前提(state への書き込み権限を持たない)。
resource "aws_iam_role_policy_attachment" "github_plan_readonly" {
  role       = aws_iam_role.github_plan.name
  policy_arn = "arn:aws:iam::aws:policy/ReadOnlyAccess"
}

# ReadOnlyAccess には secretsmanager:GetSecretValue が含まれないが、
# aws_secretsmanager_secret_version の refresh で必要になる
data "aws_iam_policy_document" "github_plan_secrets" {
  statement {
    sid       = "ReadProjectSecrets"
    actions   = ["secretsmanager:GetSecretValue"]
    resources = ["arn:aws:secretsmanager:${var.region}:${data.aws_caller_identity.current.account_id}:secret:${var.project}*"]
  }
}

resource "aws_iam_role_policy" "github_plan_secrets" {
  name   = "plan-secrets"
  role   = aws_iam_role.github_plan.id
  policy = data.aws_iam_policy_document.github_plan_secrets.json
}
