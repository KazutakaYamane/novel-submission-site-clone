# terraform/bootstrap

全 root module の state を置く S3 バケットを作る、一度きりの root module。

## 何を作るか
`aws_s3_bucket.tfstate`(`<project>-tfstate-<account_id>`)。バージョニング / SSE-S3 / Public Access Block / TLS 強制 / 古いバージョンの自動失効を有効化し、`prevent_destroy = true` を付ける。

## 手順(初回のみ)

```bash
cd terraform/bootstrap
terraform init
terraform apply
```

出力 `backend_snippet` を各 root(`environments/prod/`、`environments/prod-persistent/`)の `backend.tf` に貼り、`key` を root ごとに変える。

## 注意

- ローカルの `terraform.tfstate` はコミットしない(`.gitignore` で除外済み)。
- `terraform destroy` は実行しない。

## state 紛失時の復旧
AWS 上のバケットが残っていれば import で管理下に戻せる。

```bash
terraform init
terraform import aws_s3_bucket.tfstate novel-submission-site-clone-tfstate-<account_id>
# versioning / encryption / policy 等のサブリソースも同様に import
terraform plan   # 差分がないことを確認
```
