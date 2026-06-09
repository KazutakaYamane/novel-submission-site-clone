# terraform/bootstrap

Terraform state 基盤(state 保存用 S3 バケット)を作るための **一度きり** の root module。state ロックは DynamoDB ではなく **S3 ネイティブロック**(各 root module の backend で `use_lockfile = true`)を使うため、ロック専用リソースは作らない。

## なぜ別構成なのか(鶏卵問題)

通常の root module は state を S3 バックエンドに保存するが、その S3 バケット自体を Terraform で管理しようとすると「state 置き場を作るのに state 置き場が必要」という循環が発生する。

本プロジェクトでは以下の方針で解決する:

- bootstrap を **state 置き場専用の独立 root module** として切り出す
- bootstrap の state は **ローカルに置く**(S3 バックエンドを使わない)
- bootstrap は最初の `apply` 後は **管理対象外** とし、普段のオペレーションでは触らない
- 復旧時のみ、後述の手順で再構築または import する

この方針は他の root module(`environments/prod/` 等)の `backend "s3"` ブロックが、bootstrap で作ったバケットとロックテーブルを参照する形で完成する。

## 何を作るか

| リソース | 既定名 | 用途 |
|---|---|---|
| `aws_s3_bucket.tfstate` | `novel-submission-site-clone-tfstate-<account_id>` | 全 root module の state ファイル置き場 |

S3 はバージョニング / SSE-S3 / Public Access Block / TLS 強制ポリシー / 古いバージョンの自動失効を有効化。
state ロックは同じバケット上の `<key>.tflock` オブジェクト(S3 条件付き書き込み)で実現するため、専用テーブルは不要。
S3 バケットには `prevent_destroy = true`。

## 前提

- AWS CLI が `aws sts get-caller-identity` で本人確認できる状態(プロファイル or 環境変数)。
- 初回 apply 時のみ、ローカルの IAM 認証が必要(`s3:CreateBucket` / バケット設定系の権限)。

## 手順(初回のみ)

```bash
cd terraform/bootstrap
terraform init        # backend は local。S3 を使わない。
terraform plan
terraform apply
```

実行後、出力 `backend_snippet` をコピーして本体構成
(`terraform/environments/prod/`)の `backend "s3"` ブロックに貼る。

```hcl
backend "s3" {
  bucket       = "novel-submission-site-clone-tfstate-123456789012"
  key          = "prod/terraform.tfstate"
  region       = "ap-northeast-1"
  use_lockfile = true
  encrypt      = true
}
```

## 注意

- 本ディレクトリの `terraform.tfstate` は **コミットしない**(`.gitignore` で除外済み)。
- 普段のオペレーションでは触らない。S3 バケットの設定変更が必要なときだけ、ローカルから再度 `apply`。
- このディレクトリで `terraform destroy` は実行しないこと(`prevent_destroy = true` で防護はしている)。

## 紛失・破損時の復旧

bootstrap の `terraform.tfstate`(ローカル)が失われても、AWS 上のリソース本体が残っていれば Terraform 管理下に戻せる。

```bash
cd terraform/bootstrap
terraform init
terraform import aws_s3_bucket.tfstate                    novel-submission-site-clone-tfstate-<account_id>
# 各 S3 関連サブリソース(versioning, encryption, policy 等)も同様に import。
terraform plan   # 差分がないことを確認
```

AWS 上のリソース自体が消えた(`prevent_destroy` を解除して手動削除した等)場合は、bootstrap を再 `apply` して作り直す。ただし他 root module の既存 state が新バケットに無い場合は、別途 `terraform state pull` / `push` で移送する必要がある。
