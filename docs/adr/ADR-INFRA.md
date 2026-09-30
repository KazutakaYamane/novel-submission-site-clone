# ADR-INFRA: インフラ構成の採用理由

- **ステータス**: 採用
- **最終更新**: 2026-09-30

本プロジェクトはカクヨムを模した小説投稿サイトである。フロントエンドは Next.js、バックエンドは Laravel(JSON API)で構成し、AWS 上のインフラを Terraform で管理する。

Next.js と Laravel は、それぞれ別の ECS on Fargate サービスとして動かす。2022 年の登壇資料によると、カクヨムは Next.js の SSR サーバーを Web アプリ本体と同じく ECS Fargate で動かしている([カクヨムの10年を見据えた技術 インフラ編 ── Hatena Engineer Seminar #19](https://speakerdeck.com/7474/kakuyomufalse10nian-wojian-ju-etaji-shu-nil-nil-inhurabian-nil-nil-awsakauntotoiaasfalseduan-jie-de-yi-xing-nil-nil-hatena-engineer-seminar-number-19))。本プロジェクトはこの構成に合わせる。

**却下した案**: Next.js を Vercel や AWS Amplify Hosting で動かす案。SSR/ISR の運用は楽になるが、登壇資料のカクヨムの構成と異なるため採用しない。

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

このプロジェクトではページごとに SSR と ISR を使い分ける。作品や話のページは ISR で表示を速くする。作者が新しく話を公開したときは、バックエンドが Next.js に `revalidateTag`(指定タグのキャッシュ破棄)を依頼し、公開をすぐに反映させる。マイページのような個人向けページは SSR でリクエストごとに生成する。

ECS 上でこの SSR/ISR を動かすために、以下の 3 点を決めた。
- SSR から API をどう呼ぶか
- ISR キャッシュをどこに置くか
- HTML をどこでキャッシュするか

---

## SSR から API をどう呼ぶか

**問題**: SSR からブラウザと同じく CloudFront 経由でバックエンドを呼ぶと、バックエンドから見た送信元 IP が全て Next.js ECS タスクの IP になり、IP 単位のレート制限が効かない。

**採用**: バックエンドに Service Connect 専用のポート 8080 を設け、SSR は `http://api:8080` を呼び、ユーザーの IP を渡すようにする。ブラウザからは CloudFront → ALB 経由でポート 80 の `/api/*` を呼ぶ。

**代償**: API クライアントで、サーバー側は `http://api:8080`、ブラウザ側は `/api` と base URL を切り替える必要がある。

## ISR キャッシュをどこに置くか

**問題**: ISR の既定キャッシュは ECS タスクのローカルディスクにある。タスクが複数あると、バックエンドからの `revalidateTag` は 1 つのタスクにしか届かず、他のタスクは古いページを返し続ける。

**採用**: ElastiCache(Redis)を置き、Next.js の自作 `cacheHandler` から使う。キーにビルド ID を含め、デプロイ後は新しいビルドのキャッシュだけを使う。

**補足**: バックエンドも複数タスクで動くため、セッションやキューをタスク内に置くと、タスク間でデータを共有できない問題が起きる。ALB が別のタスクに振り分けるとログインが切れ、タスクが止まると未処理のジョブが消える。バックエンドのセッション・キャッシュ・キューも同じ ElastiCache に置き、この問題を解決する。

**代償**: ISR キャッシュとバックエンドが 1 つの Redis のメモリを取り合う。ISR キャッシュでメモリが埋まると、セッションが追い出されたり、キューへの書き込みが失敗したりする恐れがある。

## HTML をどこでキャッシュするか

**問題**: CloudFront はオリジンの `Cache-Control` に従うので、Next.js 側のキャッシュと CloudFront 側のキャッシュの両方で HTML がキャッシュされる。バックエンドからの `revalidateTag` は Next.js のキャッシュしか破棄しないので、CloudFront のキャッシュが破棄されない。

**採用**: HTML を返す `default` のキャッシュ動作に `Managed-CachingDisabled` を設定し、CloudFront では HTML をキャッシュさせない。

**却下した案**: キャッシュの破棄が必要なときに CloudFront の `CreateInvalidation` も呼ぶ案。CloudFront のキャッシュは URL (ワイルドカード可)でしか破棄できない。1 つのタグに関係するページはトップページやランキングなど別々の URL に散らばるため、バックエンドがタグごとに破棄する URL の一覧を持つ必要がある。

**代償**: HTML へのリクエストはすべて東京リージョンの Next.js まで届く。
- 海外の読者へのレスポンスが遅くなる。
- アクセスが集中したとき、負荷を CloudFront で吸収できず、Next.js と ElastiCache が直接負荷を受ける。