# terraform/environments/prod

prod 環境のルート構成。`terraform/modules/*` を組み合わせてフェーズ1 のウォーキングスケルトンを構築する。

## 前提

- `terraform/bootstrap/` の apply が完了し、state 用 S3 バケットが存在すること(ロックは S3 ネイティブロック。README は [bootstrap/README.md](../../bootstrap/README.md))。
- 同一アカウントに Route 53 public hosted zone(`var.domain_name` のゾーン)が存在し、NS をレジストラに登録済みであること。

## 初回手順

1. `backend.tf` の `<ACCOUNT_ID>` を実アカウント ID に置換する。
   値は `terraform -chdir=../../bootstrap output backend_snippet` で確認できる。
2. `cp terraform.tfvars.example terraform.tfvars` して `domain_name` を編集。
3. `terraform init` → `terraform plan` → `terraform apply`。

## フェーズ1 着手範囲(順次追加)

| ステップ | 内容 |
|---|---|
| ステップ0 | `versions.tf` / `providers.tf` / `backend.tf` / `variables.tf` ✓ |
| ステップ1 | `modules/network`(VPC / subnet / SG) ✓ |
| ステップ2 | `modules/secrets`(Secrets Manager: DB password / APP_KEY) ✓ |
| ステップ3 | ECR、Route 53 ホストゾーン参照 |
| ステップ4 | `modules/database`(RDS for MySQL) |
| ステップ5 | `modules/ecs-service`(ALB + ECS + ACM ap-northeast-1) |
| ステップ6 | `modules/cloudfront`(S3 + CloudFront + ACM us-east-1) |

詳細とフェーズ計画は `docs/portfolio-project-knowledge.md` §13 を参照。
