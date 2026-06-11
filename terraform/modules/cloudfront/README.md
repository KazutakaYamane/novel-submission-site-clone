# terraform/modules/cloudfront

配信層(CDN)を構築するモジュール。ACM(us-east-1)が必要なため provider alias を受け取る。

## 構成

- **ACM(us-east-1)**: viewer 向け証明書。検証 CNAME は ALB 用(ap-northeast-1)と同名・同値になるため `allow_overwrite` で共存
- **S3(静的アセット)**: `/_next/static/*` の配信元。OAC で CloudFront からのみ読み出し可。UGC 用バケットとは別(公開ポリシーが異なる。フェーズ3 で追加)
- **CloudFront Distribution**(ビヘイビア分離が本モジュールの核心。§11.1):

| パス | オリジン | キャッシュ |
|---|---|---|
| `default` | ALB(`origin.<domain>`) | `UseOriginCacheControlHeaders-QueryStrings`: ISR の `s-maxage` を尊重し、**個人化 SSR は Next.js が返す `private`/`no-store` で非キャッシュ**(誤ると他人のログイン状態を配信する事故) |
| `/api/*` | ALB(同上) | `CachingDisabled`(JSON API) |
| `/_next/static/*` | S3(OAC) | `CachingOptimized`(content hash 付きのため長期 immutable) |

- **Route 53**: `<domain>` A/AAAA(Alias) → Distribution

## 設計判断

- **オリジンは `origin.<domain>`(ALB への Alias)**: CloudFront のオリジン TLS は SNI = オリジンの FQDN で検証されるため、ALB 証明書のワイルドカード SAN がカバーする専用名を切る。viewer 向け `<domain>` とは分離。
- **origin request policy は `AllViewer`**: Host・cookie・全ヘッダをオリジンへ転送し、Laravel / Next.js が公開ドメインで URL 生成できるようにする。キャッシュキーは cache policy 側で別管理(転送 ≠ キャッシュキー)。
- **PriceClass_200**: PriceClass_100 は北米・欧州のみで日本のエッジが外れるため。

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `distribution_id` | デプロイ時の invalidation |
| `static_assets_bucket` | `aws s3 sync` の宛先 |
