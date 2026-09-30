# terraform/modules/secrets

RDS マスターパスワードと Laravel APP_KEY を Terraform 側で生成し、AWS Secrets Manager に保存するモジュール。

## 作成リソース

| リソース | 値 | 用途 |
|---|---|---|
| `random_password.db_master` | 32 文字、`!#$%&*+-=?_` 限定の特殊文字 | RDS の master_password。RDS が受け付けない `/` `@` `"` スペースを避けるため特殊文字を絞る |
| `random_id.app_key` | 32 バイト乱数 | Laravel APP_KEY の原データ。`base64:<b64_std>` 形式で保存(`php artisan key:generate` と同じ書式) |
| `aws_secretsmanager_secret.db_password` | name: `<project>-<env>/rds/master-password` | RDS パスワード |
| `aws_secretsmanager_secret.app_key` | name: `<project>-<env>/laravel/app-key` | Laravel APP_KEY |

## 主要 outputs

| 名前 | sensitive | 用途 |
|---|---|---|
| `db_password_secret_arn` | × | ECS Task Definition の `secrets` ブロック |
| `db_password_value` | ○ | database モジュールへの master_password 配線 |
| `app_key_secret_arn` | × | ECS Task Definition の `secrets` ブロック(APP_KEY 注入) |
