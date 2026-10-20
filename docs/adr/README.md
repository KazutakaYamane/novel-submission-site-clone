# ADR

1ファイルに1つの決定を書く。ファイル名は`NNNN-<slug>.md`で、番号は決めた順に振る。決定を変えるときは新しいADRを足し、古いADRのステータスを`superseded by`にする。

| 番号 | 決定 | ステータス |
|---|---|---|
| [0001](0001-ecs-fargate.md) | Next.jsとLaravelを別々のECS on Fargateサービスで動かす | 採用(一部を [0010](0010-browser-talks-only-to-nextjs.md) が置き換え) |
| [0002](0002-ssr-isr-per-page.md) | ページごとにSSRとISRを使い分ける | superseded by [0009](0009-cache-components.md) |
| [0003](0003-ssr-calls-api-via-service-connect.md) | SSRからAPIをどう呼ぶか | superseded by [0010](0010-browser-talks-only-to-nextjs.md) |
| [0004](0004-redis-shared-cache.md) | ISRキャッシュをどこに置くか | 採用(一部を [0009](0009-cache-components.md)・[0011](0011-sanctum-api-token.md) が置き換え) |
| [0005](0005-cloudfront-no-html-cache.md) | HTMLをどこでキャッシュするか | 採用 |
| [0006](0006-ci-cd.md) | CI/CDをどう構成するか | 採用 |
| [0007](0007-prod-cost-compromises.md) | 運用コストを抑えるための構成上の妥協 | 採用(一部を [0010](0010-browser-talks-only-to-nextjs.md) が置き換え) |
| [0008](0008-backend-layers.md) | バックエンドの層の分け方 | 採用(一部を [0011](0011-sanctum-api-token.md) が置き換え) |
| [0009](0009-cache-components.md) | Next.js のキャッシュを Cache Components で構成する | 採用(実装前) |
| [0010](0010-browser-talks-only-to-nextjs.md) | ブラウザは Next.js とだけ通信する | 採用(実装前) |
| [0011](0011-sanctum-api-token.md) | Sanctum の API トークンで認証する | 採用(実装前) |
