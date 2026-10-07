# terraform/modules/ecs-service

Next.js(web)と Laravel(api)の実行基盤一式を構築するモジュール。

## 構成
| Service | コンテナ | サイズ | Service Connect |
|---|---|---|---|
| web (Next.js) | web(:3000) | 0.5vCPU/1GB ARM64 | クライアントのみ(`http://api:8080` を呼ぶ) |
| api (Laravel) | nginx(:80 ALB 用、:8080 Service Connect 用) + app(php-fpm:9000) | 0.25vCPU/0.5GB ARM64 | サーバー(`api:8080` で公開) |


## ALB の転送元検証
`CloudFront`が付ける`X-Origin-Verify`ヘッダーの値が一致するリクエストだけを、リスナールール(`web`: priority 100、`api`: priority 10)でターゲットグループに転送する。一致しないリクエストはリスナーのデフォルトアクション(固定レスポンス403)で拒否する。`alb_dns_name`や`origin.<domain>`をヘッダーなしで直接叩くと403になる。

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `origin_domain_name` | cloudfront モジュールのオリジン |
| `cluster_name` / `web_service_name` / `api_service_name` | デプロイ(`aws ecs update-service`) |
| `alb_dns_name` | 疎通確認(`X-Origin-Verify`ヘッダーが必要) |
