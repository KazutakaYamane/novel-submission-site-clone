# ADR-INFRA: インフラ構成の採用理由

**ステータス**: 採用
**最終更新**: 2026-06-09（セッション7: フロントを Next.js on ECS=方式A に変更したことに伴い、決定6〜8 を追加。ElastiCache 採否をフェーズ4 から本 ADR で確定に前倒し）

## コンテキスト

本プロジェクトの設計上の主題はバックエンドの設計力提示と、AWS でのインフラ・CI/CD・運用の構築力提示にある（§1）。本 ADR は後者のうち、コンピュート基盤・ネットワーク・データストア・リポジトリ構成に関わるインフラ・構成レベルの判断をまとめて扱う。記述方針は ADR 共通方針（knowledge doc §10.0）に従い、応募者の制作事情は書かず、理由はアプリケーションの要件と設計主題から導く。

フロントエンドのレンダリング戦略そのもの（ページ単位の SSG/SSR/ISR 採用理由）は ADR-FE で扱い、本 ADR ではそれを「どう動かすか」（実行基盤・通信・キャッシュ）を扱う。

---

## 決定1: コンテナ実行基盤に通常の ECS on Fargate を採用する（Express Mode を却下）

**決定**: ALB / Target Group / Listener / ECS Cluster / Service / Task Definition / Application Auto Scaling を Terraform で個別に明示記述する通常構成の ECS on Fargate を採用する。`terraform-aws-modules/ecs/aws` の標準 `service` サブモジュールを薄くラップする。

**理由**:
1. ポートフォリオとして VPC / ALB / Target Group / Auto Scaling Policy / CloudWatch Alarm を**明示的に IaC で記述**し、構築力を可視化したい。
2. Nginx + php-fpm のマルチコンテナ構成、および ISR/セッション用の周辺構成と整合する。
3. はてなのインフラ方向性（EC2 → マネージド/コンテナ化への移行中、§14.3）と噛み合い、面接の会話が成立しやすい。

**却下した代替案**:
- **ECS Express Mode**: ALB + Fargate + Auto Scaling + CloudWatch を一括構築でき、追加料金もない妥当な選択肢である。しかし (1) サイドカーコンテナ非対応で Nginx + php-fpm 構成および後述のマルチサービス構成と矛盾、(2) ALB/TG/ACM/Auto Scaling を単一リソースに隠蔽するため Terraform state が「実体」を持たず IaC 可視性が低い、(3) 2025-12 発表で実務採用例が乏しく、はてなの文脈との噛み合わせが弱い。本プロジェクトの「IaC 可視性」という要件に対して不採用とした。
- **AWS App Runner**: 2026-04-30 に新規受付終了済みのため対象外。

**結果**: ECS のリソース構成が Terraform 上に明示され、ALB/TG/Listener/Auto Scaling の依存関係を IaC として示せる。

---

## 決定2: Graviton (ARM64) を採用する

**決定**: Fargate のアーキテクチャを ARM64（Graviton）とする。

**理由**: 同性能で約20%安。Laravel + MySQL + Node(Next.js) はいずれも ARM64 に完全対応。

**結果**: コンテナイメージは ARM64 でビルドする。CI は GitHub Actions の x86 ランナーからクロスビルド（または ARM ランナー）する。Next.js の画像最適化に用いる `sharp` 等のネイティブ依存は ARM64 向けに解決する必要がある（ADR-FE / knowledge doc §11.1 参照）。

---

## 決定3: NAT Gateway を使用しない

**決定**: ECS タスクは public subnet に配置し Public IP を付与して外向き通信を行う。RDS / ElastiCache は private subnet に置く。NAT Gateway は構築しない。

**理由**: 低トラフィックのポートフォリオに対し NAT Gateway（時間課金 + データ処理課金）は過剰。タスク数が少なく Public IP 課金の方が安い。

**重要な含意（決定7 と関連）**: NAT が必要なのは「VPC 内 → インターネット」の外向き通信のみ。VPC 内のタスク間通信（awsvpc の ENI 同士、プライベート IP）・ElastiCache・RDS への通信は VPC のローカルルートで完結し、NAT も IGW も経由しない。したがって後述の East-West 通信（Service Connect）は NAT 不使用設計と矛盾しない。

**却下した代替案**:
- **private subnet + NAT Gateway**: セキュリティ的に教科書的だが、本規模ではコスト過剰。
- **Fargate Spot**: 中断耐性の議論が必要になるため初期は通常 Fargate。

**結果**: Public IPv4 課金（$0.005/h/IP、2024年有料化）を受容する。タスク数増は Public IP 増に直結する点に留意（決定6 でサービスが2系統になるため）。

---

## 決定4: RDB に MySQL 8.0 を採用する

**決定**: RDS for MySQL 8.0。文字コード utf8mb4 / collation utf8mb4_unicode_ci。

**理由**: カクヨム実環境が MySQL であり、実環境との整合を優先（§4）。日本語全文検索は InnoDB FULLTEXT + ngram parser を第一候補、要件次第で Meilisearch / OpenSearch を併用。UUID は `Str::uuid()` でアプリ生成。

**結果**: Single-AZ 運用でも 2 AZ 分のサブネットグループ登録が必要（§11.1）。

---

## 決定5: モノレポを採用する

**決定**: アプリ（Laravel / Next.js）/ Docker 設定 / Terraform / CI ワークフローを1リポジトリに同居させる。

**理由**: (1) 開発者1人・サービス1つ・環境1つ（prod のみ）の規模にマルチリポはオーバーキル、(2) レビュアーが1 URL で全体を把握でき応募文脈で有利、(3)「インフラもプロダクトの一部」という現代的感覚と整合。「ベストプラクティスだから」ではなく「この規模・目的に対して妥当だから」採用する。

**結果**: `app/`（Laravel）・`frontend/`（Next.js）・`terraform/`・`.github/` が単一リポジトリに同居する。

---

## 決定6: フロントエンドを独立した ECS サービス（Next.js / Node ランタイム）として実行する（方式A）

**決定**: Next.js（React + TypeScript、ページ単位の SSG/SSR/ISR — レンダリング戦略の理由は ADR-FE）を、Laravel API とは別の ECS on Fargate サービスとして実行する。両サービスを単一の内部向けでない公開 ALB の背後に置き、リスナールールでパス振り分けする:
- `default` behavior → ALB → **Next.js TG**（Node :3000）
- `/api/*` behavior → ALB → **Laravel TG**（nginx :80 → php-fpm）

CloudFront は default を ALB(Next.js) に、`/api/*` を ALB(Laravel) に、`/_next/static/*` を長期 immutable キャッシュに振り分ける。

**理由**:
1. SSR/ISR は Node ランタイムの常駐を要するため、純静的な S3 配信（旧 SPA 構成）では実現できない。レンダリング戦略を ADR-FE で Next.js に決定したことの直接の帰結。
2. Next.js と Laravel は**スケーリング特性・デプロイ頻度・故障モードが異なる**（Node のイベントループ/メモリ vs php-fpm のワーカー枯渇）。独立サービスにすることで個別にスケールし、片方の障害が他方のデプロイを巻き込まない。
3. 同一タスク内同居（localhost 通信）案より、責務境界がサービス境界として明確になる。

**却下した代替案**:
- **Next.js を S3 静的配信（純 SPA / 純 SSG エクスポート）**: SSR と ISR が使えず、ページ単位のレンダリング最適化（ADR-FE の主題）が成立しない。
- **Next.js を Laravel と同一 ECS タスクに同居**: localhost 通信で East-West は単純になるが、フロントとバックのデプロイ・スケールが密結合し、決定6 の利点（独立スケール・障害分離）を失う。
- **OpenNext で Lambda + S3 + CloudFront（serverless / 方式B）**: 運用負荷は低いが、本プロジェクトは ECS による IaC 可視化（決定1）を主題に置いており、コンピュート基盤を ECS に統一する方が構成の一貫性とポートフォリオの訴求が高い。

**結果**:
- デプロイ対象が2系統（Laravel イメージ + Next.js イメージ）になり、ECR / ECS デプロイ・ヘルスチェック・Auto Scaling・`concurrency` 制御が各サービス分に増える。
- Laravel への入口が2系統になる（ブラウザからの公開 `/api/*` と、SSR からの East-West。決定7 参照）。
- CloudFront のキャッシュビヘイビアをパス単位で設計する必要が生じる。**個人化された SSR レスポンスをキャッシュしない**ビヘイビア分離は、誤ると他人のログイン状態を配信する事故になるため最優先で設計する（§11.1）。

---

## 決定7: サービス間（East-West）通信に ECS Service Connect を採用する

**決定**: Next.js サーバーが SSR/ISR のデータ取得で Laravel API を叩く East-West 通信に、ECS Service Connect（Cloud Map 名前空間 + 自動注入の Envoy サイドカー）を採用する。Laravel サービスを論理名 `api`（client alias `http://api:80`）で公開し、Next.js サービスは同名前空間のクライアントとして解決する。ブラウザからのクライアント側 fetch / ミューテーションは従来どおり公開 `/api/*`（North-South）を使い、SSR サーバーサイド fetch のみ East-West に分ける。

**理由**:
1. SSR の API 取得を公開 ALB に通すと IGW 経由のヘアピン（レイテンシ・データ転送課金・SG が不明瞭）になる。Service Connect は通信を VPC 内に閉じ、NAT 不使用設計（決定3）と整合する。
2. Service Connect は追加コスト $0（Envoy のメモリ微増のみ）で、**クライアント側ロードバランシング・リトライ・トラフィック/エラー/レイテンシのメトリクスを内蔵**する。観測性レイヤ（§13.5、歓迎要件）にそのまま寄与する。

**却下した代替案**:
- **ECS Service Discovery（Cloud Map DNS のみ）**: 追加コストは同じ $0 だが、DNS ラウンドロビンで負荷分散が弱く、リトライもメトリクスも持たない。Service Connect の下位互換であり不採用。
- **内部 ALB（2本目の internal ALB）**: 本格的なロードバランシングとヘルスチェックが得られるが、約 +$20/月 のコストと構築が本規模に対して過剰。
- **公開 ALB ヘアピン**: SSR も公開 ALB を経由する案。ヘアピンのレイテンシ・転送課金・SG の不明瞭さを避けるため不採用。

**結果**:
- Cloud Map の HTTP 名前空間を1つ作成する。各 ECS サービスに `service_connect_configuration` を設定（Laravel は `service` ブロックで公開、Next.js はクライアントとして参加）。
- ECS のセキュリティグループを **Next.js 用 / Laravel 用に分離**し、Laravel タスク SG に Next.js タスク SG からの 80 番 ingress を許可する East-West ルールを追加する。`network` モジュールの SG 設計に手が入る。
- Next.js アプリ側に「サーバー実行時は内部 URL（`http://api:80`）、クライアント実行時は公開パス（`/api`）」を出し分ける層を設ける。

---

## 決定8: ISR 共有キャッシュ / KVS に ElastiCache を採用する（sidecar Redis を却下、フェーズ1で確定）

**決定**: Redis を ElastiCache（マネージド、private subnet、Single-AZ）として構築し、(1) Next.js の ISR 共有キャッシュ（custom `cacheHandler` の backend）、(2) Laravel のセッション / キャッシュ / キューの両方に用いる。本判断はフェーズ4 ではなく**フェーズ1（縦串）で確定**する。

**理由**:
1. ISR の既定キャッシュは `.next/cache`（タスクローカルのファイルシステム）であり、Fargate では**タスク間で共有されず再デプロイで揮発する**。マルチタスクで一貫した ISR・オンデマンド再検証（`revalidatePath`/`revalidateTag`）を成立させるには**タスク横断の共有ストアが必須**。
2. 共有ストアが必須である以上、揮発・タスクローカルな sidecar Redis は ISR キャッシュの要件を満たせない。マネージドで永続的かつ全タスクから共有可能な ElastiCache が必要になる。
3. RDS パスワード同様、キャッシュ基盤を縦串の段階で組み込まないと後付けの手戻りコストが高い。

**却下した代替案**:
- **sidecar Redis（ECS タスク内 Redis コンテナ）**: コストは安い（旧試算で月 −約2,000円）が、タスクローカル・揮発のため**マルチタスクの ISR 共有キャッシュとして機能しない**。決定6 で Next.js を独立スケールするサービスにした以上、タスク横断共有が要件となり成立しない。旧 knowledge doc §6.4 で「フェーズ4 で判断」としていた本論点は、ISR 採用により本 ADR で ElastiCache 確定として解決する。
- **S3 backend の cacheHandler**: ElastiCache 不要にできるが、レイテンシが大きく、Laravel のセッション/キャッシュ/キューには別途 Redis が要るため二重管理になる。Redis に一本化する方が単純。

**結果**:
- 月額に ElastiCache（cache.t4g.micro Single-AZ、約 +$13/月）が**固定費として加わり、最安だった sidecar Redis 構成は選択肢から外れる**（§6.3 再試算）。
- ElastiCache の SG は Next.js タスク SG（ISR cacheHandler）と Laravel タスク SG（セッション等）の両方から 6379 ingress を許可する。Redis 接続文字列は Secrets Manager 経由で両サービスに注入する。
- Service Connect は HTTP ベースのため Redis（TCP/RESP）には用いず、ElastiCache へは素の SG 許可 + 接続文字列で接続する。

---

## 全体の結果（この ADR の帰結）

- コンピュートは ECS on Fargate に統一（Laravel API サービス + Next.js サービスの2系統）。
- ネットワークは NAT 不使用・public subnet 配置を維持しつつ、East-West は Service Connect で VPC 内に閉じる。
- データストアは RDS for MySQL（private） + ElastiCache（private、ISR/セッション共有）。
- これらをすべて Terraform で明示記述し、IaC・CDN・サービス間通信・キャッシュ設計の運用力を可視化する。
