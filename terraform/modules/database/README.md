# terraform/modules/database

RDS for MySQL を構築するモジュール。

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `endpoint_address` | ECS タスクの `DB_HOST` |
| `db_name` / `master_username` | ECS タスクの `DB_DATABASE` / `DB_USERNAME` |
