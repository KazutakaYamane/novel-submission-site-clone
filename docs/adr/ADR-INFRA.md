# ADR-INFRA: インフラ構成の採用理由

- ステータス: 採用
- 最終更新: 2026-09-30

本プロジェクトはカクヨムを模した小説投稿サイトである。フロントエンドはNext.js、バックエンドはLaravel(JSON API)で構成し、AWS上のインフラをTerraformで管理する。

Next.jsとLaravelは、それぞれ別のECS on Fargateサービスとして動かす。2022年の登壇資料によると、カクヨムはNext.jsのSSRサーバーをWebアプリ本体と同じくECS Fargateで動かしている([カクヨムの10年を見据えた技術 インフラ編 ── Hatena Engineer Seminar #19](https://speakerdeck.com/7474/kakuyomufalse10nian-wojian-ju-etaji-shu-nil-nil-inhurabian-nil-nil-awsakauntotoiaasfalseduan-jie-de-yi-xing-nil-nil-hatena-engineer-seminar-number-19))。本プロジェクトはこの構成に合わせる。

**却下した案**: Next.jsをVercelやAWS Amplify Hostingで動かす案。SSR/ISRの運用は楽になるが、登壇資料のカクヨムの構成と異なるため採用しない。

```
CloudFront ─┬─ default                    → ALB → Next.js
            ├─ /api/*                     → ALB → Laravel :80
            ├─ /_next/image*              → ALB → Next.js
            └─ /_next/static/*, /static/* → S3

Next.js ─(Service Connect)→ Laravel :8080

Next.js ─┬─→ ElastiCache (Redis, Next.js と Laravel で共用)
Laravel ─┘
Laravel ───→ RDS MySQL
```

このプロジェクトではページごとにSSRとISRを使い分ける。作品や話のページはISRで表示を速くする。作者が新しく話を公開したときは、バックエンドがNext.jsに`revalidateTag`(指定タグのキャッシュ破棄)を依頼し、公開をすぐに反映させる。マイページのような個人向けページはSSRでリクエストごとに生成する。

ECS上でこのSSR/ISRを動かすために、以下の3点を決めた。
- SSRからAPIをどう呼ぶか
- ISRキャッシュをどこに置くか
- HTMLをどこでキャッシュするか

---

## SSRからAPIをどう呼ぶか

**問題**: SSRからもブラウザと同じくCloudFront経由でバックエンドを呼ぶと、バックエンドに届く送信元IPがすべてNext.js ECSタスクのIPになる。このためIP単位のレート制限が機能しない。

**採用**: バックエンドにService Connect専用のポート8080を設ける。SSRは`http://api:8080`を呼び、ユーザーのIPを渡す。ブラウザはCloudFront → ALB経由で、ポート80の`/api/*`を呼ぶ。

**代償**: APIクライアントは、サーバー側で`http://api:8080`、ブラウザ側で`/api`とbase URLを切り替える必要がある。

## ISRキャッシュをどこに置くか

**問題**: ISRの既定キャッシュはECSタスクのローカルディスクにある。タスクが複数あると、バックエンドからの`revalidateTag`は1つのタスクにしか届かず、他のタスクは古いページを返し続ける。

**採用**: ElastiCache(Redis)を置き、Next.jsの自作`cacheHandler`から使う。キーにビルドIDを含め、デプロイ後は新しいビルドのキャッシュだけを使う。

**補足**: バックエンドも複数タスクで動くため、セッションやキューをタスク内に置くと、タスク間でデータを共有できない。ALBが別のタスクに振り分けるとログインが切れ、タスクが止まると未処理のジョブが消える。そこでバックエンドのセッション・キャッシュ・キューも同じElastiCacheに置く。

**代償**: ISRキャッシュとバックエンドが1つのRedisのメモリを取り合う。ISRキャッシュでメモリが埋まると、セッションが追い出されたり、キューへの書き込みが失敗したりする恐れがある。

## HTMLをどこでキャッシュするか

**問題**: CloudFrontはオリジンの`Cache-Control`に従うため、HTMLはNext.jsとCloudFrontの両方にキャッシュされる。一方、バックエンドからの`revalidateTag`が破棄するのはNext.jsのキャッシュだけで、CloudFrontのキャッシュは残る。

**採用**: HTMLを返す`default`のキャッシュ動作に`Managed-CachingDisabled`を設定し、CloudFrontではHTMLをキャッシュさせない。

**却下した案**: キャッシュの破棄が必要なときにCloudFrontの`CreateInvalidation`も呼ぶ案。CloudFrontのキャッシュはURL(ワイルドカード可)単位でしか破棄できない。1つのタグに関係するページはトップページやランキングなど別々のURLに散らばるため、バックエンドがタグごとに破棄対象のURL一覧を持つ必要がある。

**代償**: HTMLへのリクエストはすべて東京リージョンのNext.jsまで届く。
- 海外の読者へのレスポンスが遅くなる。
- アクセスが集中したとき、負荷をCloudFrontで吸収できず、Next.jsとElastiCacheが直接負荷を受ける。