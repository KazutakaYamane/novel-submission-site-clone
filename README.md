# 小説投稿サイト クローン(ポートフォリオ)

カクヨムを参考にした小説投稿・閲覧プラットフォームのクローンです。
作品の投稿・章立て・予約公開・閲覧・応援(いいね)・お気に入り・応援コメントまでを、
**設計 → 実装 → テスト → CI/CD → AWS インフラ(IaC)** の一気通貫で作っています。

Web 開発の実務経験(PHP / Laravel + React、7年)を前提に、次の 2 点を示すことを目的とした採用応募用ポートフォリオです。

1. **フレームワークに依存しないバックエンド設計力** — ドメインモデリング、状態遷移、不変条件、データ設計。個々の判断は ADR(設計判断記録)として理由・却下案つきで文書化しています。
2. **AWS でのインフラ・CI/CD 構築力** — ECS on Fargate 2 サービス構成、CloudFront、ElastiCache、RDS をすべて Terraform で明示的に記述しています。

> **デモ環境について**: 本番環境(AWS)はコスト最適化のため「作業時に `terraform apply` → 終了時に `destroy`」で運用しており(稼働時 約$50/月 → 停止時 約$0.5/月)、常時公開はしていません。ローカルは Docker のみで全機能が動きます([起動手順](#ローカルでの動かし方))。

---

## 主な機能

| 立場 | できること |
|---|---|
| ゲスト(未ログイン) | 作品一覧(更新順・ジャンル絞り込み)、作品詳細(目次)、本文閲覧、前後話への移動 |
| 読者(ログイン) | 応援(トグル式・二重応援防止)、お気に入り登録・一覧、応援コメントの投稿・削除 |
| 作者 | 作品の作成・編集・公開/非公開・完結/連載再開・削除、章立て(作成・並び替え・削除)、エピソードの下書き・公開・**予約公開**・取り下げ・改稿・削除 |
| システム | 予約時刻が来たエピソードを毎分のスケジューラで自動公開し、フロントのキャッシュ(ISR)を再検証 |

バックエンドは JSON API 約 40 エンドポイント、フロントエンドは公開ページ(一覧・目次・本文)+ 認証・マイページ・作品管理画面まで実装済みです。

## 技術スタック

| レイヤ | 技術 |
|---|---|
| バックエンド | PHP 8.4 / Laravel 13(JSON API 専用。UI 描画は持たない) |
| フロントエンド | Next.js(React + TypeScript)。ページ単位で SSG / SSR / ISR を使い分け |
| DB / KVS | MySQL 8.0(RDS)/ Redis 7(ElastiCache — セッション・キュー・ISR 共有キャッシュ) |
| インフラ | AWS: ECS on Fargate(ARM64)× 2 サービス、ALB、CloudFront、Route 53、Secrets Manager |
| IaC | Terraform(自作モジュール 6 個、環境 3 root 構成) |
| CI/CD | GitHub Actions(OIDC 認証、Pest / PHPStan / Pint / next build、イメージビルド → ECR → ECS デプロイ) |
| ローカル開発 | Docker Compose 7 サービス(ホストに PHP / Node のインストール不要) |

## アーキテクチャ

```
                        ┌─ CloudFront ─────────────────────────┐
  ブラウザ ──────────▶ │ default        → Next.js(ページ)     │
                        │ /api/*         → Laravel(JSON API)   │
                        │ /_next/static/* → 長期キャッシュ      │
                        └──────────────┬───────────────────────┘
                                       ▼
                                      ALB(パスルーティング)
                              ┌────────┴─────────┐
                              ▼                   ▼
                     ECS: Next.js サービス   ECS: Laravel サービス
                     (SSR / ISR)            (nginx + php-fpm)
                              │      ▲               │
                              │      └── Service Connect(SSR の API 取得は
                              │           VPC 内 East-West 通信で完結)
                              ▼                      ▼
                    ElastiCache(Redis)      RDS(MySQL 8.0)
                    ISR 共有キャッシュ /
                    セッション / キュー
```

ポイント:

- **フロントとバックを別 ECS サービスに分離**し、独立したスケーリングと障害分離を確保。ブラウザからの API 呼び出しは CloudFront → ALB(North-South)、SSR サーバーからの API 取得は ECS Service Connect(East-West)で VPC 内に閉じています。
- **ISR キャッシュは ElastiCache 上の自作 cacheHandler** で共有。Fargate 標準の `.next/cache` はタスクローカルで揮発するため、マルチタスクで一貫した再検証を成立させるにはタスク横断の共有ストアが必須、という判断です(ADR-INFRA 決定8)。
- **NAT Gateway 非使用**(タスクは public subnet + Public IP、DB/Redis は private subnet)など、規模に対するコスト判断も ADR に明記しています。

## 設計ハイライト

設計判断は `docs/adr/` に「決定・理由・却下した代替案・帰結」の形式で記録しています。代表例:

- **予約公開は明示的な状態機械(draft / scheduled / published)+ 毎分スケジューラ**([ADR-BE 決定1](docs/adr/ADR-BE.md))
  「published + 未来時刻」でクエリ側に判定を寄せる案は、時刻条件の書き漏らし=公開前コンテンツ漏洩という事故クラスを構造的に抱えるため却下。読み取りは `status = 'published'` だけで閉じます。
- **目次の並び替えは「最終形の全体書き換え」1 パターンに集約**([ADR-BE 決定2](docs/adr/ADR-BE.md))
  swap・章跨ぎ移動・章削除統合をすべて `PUT /works/{uuid}/structure` に落とし、個別 move/reorder API 群と中間状態の整合問題を消しています。
- **ID はハイブリッド方式(内部 BIGINT PK + 公開 UUIDv7)**([ADR-BE 決定3](docs/adr/ADR-BE.md))
  InnoDB のセカンダリ索引肥大を避けつつ、外部には連番を一切露出しません。
- **非正規化は「読み書きの頻度差」で個別判断**([ADR-BE 決定4](docs/adr/ADR-BE.md))
  一覧のソートキー(最終公開日時)は非正規化し、応援数カウンタは持たない(高頻度書き込みのホットスポットを作らない)、という対照的な 2 判断を同じ基準で下しています。
- **ISR 再検証は作品単位の粗い失効 + キュー経由でアプリの成否から分離**([ADR-BE 決定5](docs/adr/ADR-BE.md))
  「Next.js が落ちていると小説を公開できない」という不合理な結合を避け、失敗時の安全網は時間ベース revalidate に持たせます。

全体として「**クエリ側を単純に保ち、複雑さは書き込み側に寄せる**」という、読み取り比率が圧倒的に高い閲覧サイトの特性に沿った方針で統一しています。

## 品質担保

- **テスト**: Pest によるテスト 175 ケース(状態遷移・可視性・目次書き換え・API 契約を Feature テスト中心にカバー)
- **フロントエンドテスト**: Vitest + Testing Library によるユニット/コンポーネントテスト 66 ケース(自作 cacheHandler のタグ失効・Redis フォールバック、fetch 基盤の CSRF/エラー変換契約、CheerButton 等クライアントコンポーネントの楽観更新・structure PUT ペイロード形状をカバー。設計は `docs/design/frontend-test-plan.md` を正とする)
- **静的解析**: PHPStan(Larastan)level 6
- **フォーマット**: Laravel Pint
- **CI**(push / PR ごと): 上記 + フロントエンドの `tsc --noEmit` → `vitest run` → `next build`(API に到達できない環境でビルドを通し、「ビルド時に API を叩かない」という ISR 契約の回帰チェックを兼ねる)
- **CD**: GitHub Actions OIDC(長期クレデンシャルなし)で ARM64 イメージをビルド → ECR → ECS デプロイ

## コードを読む場合のガイド

限られた時間でレビューいただく場合のおすすめ順です。

| 見たいもの | 場所 |
|---|---|
| 設計判断の理由(まずここ) | [`docs/adr/ADR-BE.md`](docs/adr/ADR-BE.md) / [`docs/adr/ADR-INFRA.md`](docs/adr/ADR-INFRA.md) |
| ドメイン設計(概念・状態遷移・可視性) | [`docs/design/domain-model.md`](docs/design/domain-model.md) / [`state-machine.md`](docs/design/state-machine.md) / [`use-cases.md`](docs/design/use-cases.md) |
| API 契約・DB 設計 | [`docs/design/api-contract.md`](docs/design/api-contract.md) / [`db-schema.md`](docs/design/db-schema.md) |
| 何を意図的に作らなかったか | [`docs/design/kakuyomu-scope-cut-list.md`](docs/design/kakuyomu-scope-cut-list.md) |
| バックエンド実装の中心 | `app/app/UseCases/`(1 ユースケース = 1 クラス)、`app/app/Models/`、`app/routes/api.php` |
| 状態遷移・予約公開の実装 | `app/app/Enums/` + スケジューラコマンド(`app/app/Console/`)+ `app/app/Jobs/` |
| ISR まわり(フロント) | [`docs/design/isr-contract.md`](docs/design/isr-contract.md) / `frontend/cache-handler.mjs` / `frontend/lib/isr.ts` |
| インフラ(IaC) | `terraform/modules/`(network / database / cache / ecs-service / cloudfront / secrets)、`terraform/environments/prod/` |
| CI/CD | `.github/workflows/`(ci.yml / deploy.yml / tf-plan.yml) |

## ローカルでの動かし方

ホストに必要なのは Docker のみです。詳細な手順とトラブルシュートは [`README-docker.md`](README-docker.md) を参照してください。

```bash
cp .env.example .env
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate --seed

# 閲覧用デモデータ(作者2名・作品4件・応援/コメント)
docker compose exec app php artisan db:seed --class=DemoSeeder

# フロントエンド(別ターミナル。npm install は初回のみ)
docker compose exec web npm install
docker compose --profile frontend up web
```

起動後: フロントエンド http://localhost:3000 / API http://localhost:8080/api/v1

```bash
# テスト・静的解析・フォーマット
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/phpstan analyse
docker compose exec app ./vendor/bin/pint --test
```

## リポジトリ構成

```
├── app/                  # Laravel 13(JSON API)
├── frontend/             # Next.js(React + TypeScript)
├── docker/               # Dockerfile・nginx/php-fpm/MySQL 設定(マルチステージビルド)
├── terraform/
│   ├── bootstrap/        # tfstate 用 S3 バケット
│   ├── environments/
│   │   ├── prod-persistent/  # destroy しない資産(Route 53 zone・ECR)を state 分離
│   │   └── prod/             # 本番スタック(VPC〜CloudFront。apply/destroy 運用)
│   └── modules/          # 自作モジュール 6 個
├── .github/workflows/    # CI / デプロイ / terraform plan
└── docs/
    ├── adr/              # 設計判断記録(バックエンド / インフラ)
    └── design/           # ドメインモデル・状態遷移・API 契約・DB スキーマ 等
```
