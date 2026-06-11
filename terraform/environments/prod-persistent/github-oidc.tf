# ---------------------------------------------------------------------------
# GitHub Actions OIDC(フェーズ2 CI/CD の認証基盤)
# ---------------------------------------------------------------------------
# IAM ユーザーのアクセスキーを使わず、GitHub の短命 ID トークンで IAM ロールを
# 引き受ける(knowledge doc §9.1)。ロールは 2 つに分離する:
#   - github-deploy: master への push でのみ引き受け可。ECR push / ECS デプロイ /
#     マイグレーション one-off task / 静的アセット S3 / CloudFront invalidation。
#   - github-plan  : pull_request でのみ引き受け可。terraform plan 用の読み取り専用。
#
# prod 本体ではなくこの root に置く理由: prod は apply/destroy で回す運用のため、
# 認証基盤が prod 側にあると destroy 中は CI が認証すらできなくなる。ECR(この root)
# への push は prod が無くても通るべきなので、認証基盤も destroy されない側に置く。

data "aws_caller_identity" "current" {}

locals {
  # prod 側のリソース名は project/environment から決定的に組み立てられる
  # (prod の destroy/recreate で ARN の ID 部分が変わるものは名前ベースで縛る)
  prod_prefix          = "${var.project}-${var.environment}"
  ecs_cluster_arn      = "arn:aws:ecs:${var.region}:${data.aws_caller_identity.current.account_id}:cluster/${local.prod_prefix}"
  static_assets_bucket = "${local.prod_prefix}-static-assets"
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
      values   = ["repo:${var.github_repository}:ref:refs/heads/master"]
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

  # DescribeTaskDefinition はリソースレベル制限非対応
  statement {
    sid       = "EcsDescribeTaskDefinition"
    actions   = ["ecs:DescribeTaskDefinition"]
    resources = ["*"]
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
      values   = ["repo:${var.github_repository}:pull_request"]
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
