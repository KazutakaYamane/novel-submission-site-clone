# terraform/modules/database

RDS for MySQL を構築するモジュール。

## 環境ごとに変える変数
`multi_az`、`deletion_protection`、`skip_final_snapshot`の既定値は、apply/destroyを繰り返す運用に合わせて`false` / `false` / `true`。`environments/prod`の`db_multi_az` / `db_deletion_protection` / `db_skip_final_snapshot`から上書きする。

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `endpoint_address` | ECS タスクの `DB_HOST` |
| `db_name` / `master_username` | ECS タスクの `DB_DATABASE` / `DB_USERNAME` |
