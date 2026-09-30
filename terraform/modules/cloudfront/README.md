# terraform/modules/cloudfront

配信層(CDN)を構築するモジュール

## 構成

| パス | オリジン | キャッシュポリシー |
|---|---|---|
| `default` | ALB(`origin.<domain>`) | `CachingDisabled` |
| `/api/*` | ALB(同上) | `CachingDisabled` |
| `/_next/static/*` | S3(OAC) | `CachingOptimized` |
| `/_next/image*` | ALB(同上) | 自前(`url` / `w` / `q` と `Accept` をキーに含める) |
| `/static/*` | S3(OAC) | `CachingOptimized` |

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `distribution_id` | デプロイ時の invalidation |
| `static_assets_bucket` | `aws s3 sync` の宛先 |
