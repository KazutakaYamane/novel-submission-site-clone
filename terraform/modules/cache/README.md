# terraform/modules/cache

ElastiCache(Redis)を構築するモジュール。ADR-INFRA 決定8。

## 用途

- **Next.js の ISR 共有キャッシュ**: custom `cacheHandler` の backend。Fargate の `.next/cache` はタスクローカル・揮発のため、タスク横断の共有ストアが必須(sidecar Redis では成立しない)。
- **Laravel の session / cache / queue**。

## 構成

- `aws_elasticache_subnet_group`: private subnet × 2AZ
- `aws_elasticache_replication_group`: Redis 7.1 / `cache.t4g.micro`(Graviton)/ シングルノード / cluster mode 無効

## 設計判断

- **Single-AZ・レプリカなし**: キャッシュ用途でありノード喪失は ISR 再生成・再ログインで復旧する。Multi-AZ の固定費増に見合う可用性要件がない。
- **暗号化・AUTH なし**: private subnet + SG(web / api の ECS SG からの 6379 のみ許可)で到達経路を絞っており、TLS 導入はクライアント設定の複雑さに見合わない。
- **スナップショットなし**: 永続データを置かない前提(キューはフェーズ3 で利用開始時に要再検討)。

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `redis_url` | `redis://host:port`。secrets モジュールに渡して Secrets Manager に保存 |
| `primary_endpoint_address` / `port` | 個別参照用 |
