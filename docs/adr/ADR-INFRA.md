# ADR-INFRA: インフラ構成の採用理由

- ステータス: 採用
- 最終更新: 2026-10-07

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
---

## 運用コストを抑えるための構成上の妥協

ポートフォリオとして面接官に見せるため、`prod`を常時稼働させる。稼働時の費用は月約$50に収める(`prod-persistent`のみの状態は月約$0.50)。以下の4点は、このコスト上限のために本番相当の構成から外している。

### ECSタスクをpublic subnetに置く

**採用**: NAT Gatewayを置かない。ECSタスクはpublic subnetで`assign_public_ip = true`とし、ECR・CloudWatch Logs・Secrets Managerへ直接インターネット経由で通信する(`modules/network/main.tf`、`modules/ecs-service/services.tf`)。インバウンドはセキュリティグループで絞る。
- ALB: `CloudFront`のマネージドプレフィックスリスト(`cloudfront_origin_facing`)からの443番のみ
- web(Next.js): ALBのSGからの3000番のみ
- api(Laravel): ALBのSGからの80番と、webのSGからの8080番のみ
- RDS・ElastiCache: private subnetに置き、許可元はタスクのSGのみ

**却下した案**:
- NAT Gateway: 1台ごとに時間課金とデータ処理課金がかかり、月約$50の上限に収まらない。
- VPCエンドポイント: ECR(`api`・`dkr`)、S3、CloudWatch Logs、Secrets Managerで複数のエンドポイントが必要になる。インターフェース型は1つごとに時間課金が発生し、NATとの差が小さい。

**代償**:
- タスクがPublic IPを持つため、SGの設定を誤るとインターネットに直接露出する。
- ECSタスクのegressは`0.0.0.0/0`の全許可になる。NATなしではECRなどの宛先IPが変わるため、宛先で絞れない。
- プレフィックスリストは、全世界の`CloudFront`のIPを許可する。他人の`CloudFront`ディストリビューションからもALBのSGは通過できるため、`CloudFront`がオリジンへの転送時に付ける`X-Origin-Verify`ヘッダーをALBのリスナールールで検証する。ヘッダーの値は`modules/secrets`の`random_password.origin_verify`で生成し、`cloudfront`と`ecs-service`に渡す。一致しないリクエストは、リスナーのデフォルトアクション(固定レスポンス403)で拒否する。ヘッダー値は`CloudFront`の設定とTerraformのstateに平文で残る。
- ALBからタスクへの転送はVPC内のHTTPで、暗号化していない。
- 本番移行時はNATまたはVPCエンドポイントを置き、タスクをprivate subnetに戻す。

### RDSとElastiCacheを単一AZで動かす

**採用**: RDSは`multi_az = false`、ElastiCacheは`multi_az_enabled = false`とする。DBに入れるのはテストデータのみで、AZ障害でDBが止まっても失うものがない。

**代償**: AZ障害のときはサービスが止まる。RDSの自動バックアップ(`backup_retention_period = 7`)は稼働中のみ有効で、`destroy`すると一緒に消える。

### destroyを前提に削除保護を外す

**採用**: `prod`は`apply`と`destroy`を繰り返せるようにしている。
- RDS: `skip_final_snapshot = true`、`deletion_protection = false`。`terraform destroy`の1コマンドでDBが消える。
- Secrets Manager: `recovery_window_in_days = 0`。削除予定の状態が残ると、同名のsecretを`destroy`直後に作り直せないため、即時に完全削除する。
- `destroy`後はmigrationとseederを流し直す。

**却下した案**: 最終スナップショットを残す案。`destroy`のたびにスナップショットが増えて課金され、同名のスナップショットが残ると次回の`destroy`が失敗する。

**代償**: 誤って`destroy`するとDBの内容を復旧できない。これらの値は`environments/prod/variables.tf`の`db_multi_az` / `db_deletion_protection` / `db_skip_final_snapshot` / `secrets_recovery_window_in_days`から渡しており、本番運用に移すときは`terraform.tfvars`で上書きする。`prod-persistent`(hosted zone・ECR・GitHub OIDC)は`prod`と別のstateに分けてあり、`prod`の`destroy`では消えない。

### ECS Execを有効にしている

**採用**: `enable_execute_command = true`とし、稼働中のタスクにシェルで入ってデバッグできるようにしている。実行にはIAMの権限が必要で、権限のないユーザーは使えない。

**代償**: 本番運用では、必要なときだけ有効にする運用に変える。

### 本番運用に移すときに変更する箇所

| 項目 | 現在 | 変更後 |
|---|---|---|
| ECSタスクの配置 | public subnet + Public IP | NATまたはVPCエンドポイント + private subnet |
| RDS | `multi_az = false`、`skip_final_snapshot = true`、`deletion_protection = false` | `multi_az = true`、`skip_final_snapshot = false`、`deletion_protection = true` |
| ElastiCache | `multi_az_enabled = false` | `true`(レプリカを追加) |
| Secrets Manager | `recovery_window_in_days = 0` | 7〜30 |
| ECS Exec | 有効 | 必要時のみ有効 |
