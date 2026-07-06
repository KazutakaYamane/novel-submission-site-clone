# 実装計画: 手順と詳細仕様(実装者向けハンドオフ)

**この文書の読者は実装を担当する者(人間または AI)である。**
仕様の正は `docs/design/` の設計文書群と `docs/adr/ADR-BE.md` にあり、本書は (1) 実装の順序と各ステップの完了条件、(2) 設計文書が明文化していない実装レベルの詳細、を補う。矛盾を見つけたら**実装側で黙って逸脱せず、設計文書を直してから実装する**(implementation-rules §10)。

必読(実装前に全部読むこと):
`domain-model.md` → `state-machine.md` → `db-schema.md` → `api-contract.md` → `isr-contract.md` → `validation-spec.md` → **`implementation-rules.md`(全ステップで常時適用)** → CLAUDE.md(コマンド・環境)

ステータス: 確定(2026-07-06)
最終更新: 2026-07-06

---

## 0. 前提と共通ルール

- ホストには Docker のみ。全コマンドはコンテナ内(CLAUDE.md 参照)。`app/` `frontend/` は**まだスキャフォールドされていない**。
- 各ステップは「テスト(完了条件)が通る」まで終わらない。次ステップへ進む前に `pest` / `phpstan` / `pint --test` を通すこと。
- コミットはステップ単位以上の粒度で。1コミットに複数ステップを混ぜない。
- 実装順は依存順であり、**原則この順で進める**(手戻り最小化のため設計済み)。

## 1. 実装ステップ一覧(概観)

| Step | 内容 | 主な完了条件 |
|---|---|---|
| 0 | Laravel スキャフォールド+開発基盤 | pest/phpstan/pint が CI で動く |
| 1 | Enum・マイグレーション・シーダー・ファクトリ | 全テーブル作成、CHECK 制約動作 |
| 2 | モデル・共通基盤(uuid、可視性スコープ、ドメイン例外) | ユニットテスト |
| 3 | 認証(Sanctum SPA) | 認証系 Feature テスト |
| 4 | 公開閲覧系 API(一覧・目次・本文) | 可視性マトリクス全網羅テスト |
| 5 | 作品管理 API | U12〜U16 テスト |
| 6 | 章管理+目次全体更新(structure) | 並び替え・統合テスト |
| 7 | エピソード管理+状態遷移 | 遷移表 T1〜T10 全行テスト |
| 8 | 予約公開スケジューラ | U29 テスト(時刻固定) |
| 9 | ISR 再検証発火(Event→Job) | 発火表の全行テスト(Queue::fake) |
| 10 | 応援・お気に入り・応援コメント API | 冪等性・権限テスト |
| 11 | Next.js スキャフォールド+公開閲覧ページ(ISR) | 一覧・目次・本文が表示される |
| 12 | Next.js 再検証エンドポイント+cacheHandler | publish→ページ即時更新の E2E |
| 13 | Next.js 認証・読者機能(CSR)・管理画面(SSR) | 全ユースケース操作可能 |
| 14 | 仕上げ(デモデータ・CI・デプロイ) | 本番相当で動作 |

Step 4 までは直列必須。Step 5〜7 は相互依存が薄いが順守を推奨。Step 11 以降(フロント)は Step 10 完了後に着手する(API 契約が動くものとして固まってから)。

---

## 2. Step 0: スキャフォールドと開発基盤

1. CLAUDE.md の手順どおり `app/` に Laravel を scaffold(`composer create-project laravel/laravel . "^13.0"` → `key:generate`)。
2. `.env` / `config`: DB(`db` ホスト)、`SESSION_DRIVER=redis` / `CACHE_STORE=redis` / `QUEUE_CONNECTION=redis`(ホスト `redis`)、`config/app.php` の `timezone = 'Asia/Tokyo'`(DQ6)。メールは mailpit(将来用)。
3. パッケージ導入: `laravel/sanctum`(SPA モード)、`pestphp/pest`+`pest-plugin-laravel`、`larastan/larastan`(level: max を目標に開始時から)、`laravel/pint`。
4. `phpstan.neon` / `pint.json` をリポジトリに置き、既存 CI(`.github/workflows/`)にテスト・静的解析ステップを組み込む(既存 CI の構成を壊さないこと)。
5. ルーティング: `routes/api.php` に `/api/v1` プレフィックスのグループを作る。ヘルスチェック `GET /health` は nginx が返す既存挙動を壊さない。

**完了条件**: `docker compose exec app ./vendor/bin/pest` がサンプルテストで green。CI が回る。

## 3. Step 1: Enum・マイグレーション・シーダー

### Enum(`app/Enums/`)

```php
enum PublicationStatus: string { case Draft='draft'; case Scheduled='scheduled'; case Published='published'; }
enum WorkVisibility: string { case Public='public'; case Private='private'; }
enum SerializationStatus: string { case Ongoing='ongoing'; case Completed='completed'; }
```

### マイグレーション

`db-schema.md` §4 のとおり。作成順(FK 依存順): users(既定を改修: name 長・uuid 追加、不要カラム削除)→ genres → works → chapters → episodes → cheers → favorites → cheer_comments。

- 各テーブルの index には `-- I3: 読書順(db-schema §5)` の形式で番号コメントを残す(implementation-rules §9)。
- CHECK 制約は同一マイグレーション内で `DB::statement()`:
  - works: `CHECK (visibility IN ('public','private'))`、`CHECK (serialization_status IN ('ongoing','completed'))`
  - episodes: `CHECK (status IN ('draft','scheduled','published'))`、`CHECK ((status = 'scheduled') = (scheduled_at IS NOT NULL))`
- FK の ON DELETE は db-schema §6 の表のとおり厳守(episodes.chapter_id は **SET NULL**)。

### シーダー

genres 固定マスタ(運用値。変更可だが slug は URL に載るため後から変えない):

| slug | name |
|---|---|
| isekai-fantasy | 異世界ファンタジー |
| gendai-fantasy | 現代ファンタジー |
| sf | SF |
| renai | 恋愛 |
| mystery | ミステリー |
| horror | ホラー |

テーマカラーは `config/theme_colors.php` に **プレースホルダ配列**(`'preset-01' => '#RRGGBB'` 形式で6件程度)を置く。**54色の実値は発注者から受領後に差し替える**(Q8)。バリデーションは `Rule::in(array_keys(config('theme_colors')))`。

### ファクトリ

全モデル分。EpisodeFactory は `draft()` / `scheduled()` / `published()` の state を持ち、published は `published_at` 必須、scheduled は未来の `scheduled_at` 必須(CHECK と整合)。

**完了条件**: `migrate:fresh --seed` 成功。CHECK 制約違反の INSERT が失敗することの確認テスト。

## 4. Step 2: モデルと共通基盤

1. **公開 uuid**: トレイト `HasPublicUuid` を作る — `creating` フックで `$model->uuid = (string) Str::uuid7()`、`getRouteKeyName(): 'uuid'`。users / works / chapters / episodes / cheer_comments に適用。
2. **$fillable**: implementation-rules §2-2 のとおり、状態系・派生系カラム(status / published_at / scheduled_at / position / character_count / latest_published_at / uuid)を含めない。
3. **casts**: enum カラムは backed enum に、日時は `datetime` に。
4. **可視性スコープ**(implementation-rules §6):
   - `Work::scopeVisible($q)` → `where('visibility', WorkVisibility::Public)`
   - `Work::scopeListable($q)` → `visible()->whereNotNull('latest_published_at')`(DQ5)
   - `Episode::scopeVisible($q)` → `where('status', PublicationStatus::Published)->whereHas('work', fn($q) => $q->visible())`
5. **ドメイン例外**: `App\Exceptions\DomainRuleViolation extends \DomainException`。機械可読コードを持つ:
   ```php
   DomainRuleViolation::because('work_completed');  // code を保持
   ```
   code 一覧(api-contract §5): `work_completed` / `episode_published` / `invalid_status_transition`。例外ハンドラで **409** + `{"message": ..., "code": ...}` に写像。あわせて 401 `unauthenticated` / 404 `not_found` / 403 `forbidden` / 422 `validation_failed` の JSON 形も例外ハンドラで統一する。
6. **リレーション**: domain-model の ER のとおり。`Work::episodes()` / `Work::chapters()` / `Episode::chapter()`(nullable)等。

**完了条件**: スコープとフック(uuid 自動採番、fillable 除外)のユニットテスト。

## 5. Step 3: 認証(Sanctum SPA cookie)

- `POST /api/v1/auth/register|login|logout`、`GET /api/v1/auth/me`(api-contract §3.2)。
- SPA モード: `statefulApi()` 有効化、`SANCTUM_STATEFUL_DOMAINS` にローカル(`localhost:3000,localhost:8080`)と本番ドメイン。セッションは Redis。CSRF は `/sanctum/csrf-cookie` 前置(フロント側 Step 13 で対応)。
- register のバリデーションは validation-spec §2 User の表。

**完了条件**: 登録→ログイン→me→ログアウトの Feature テスト。未認証 me が 401 + `unauthenticated`。

## 6. Step 4: 公開閲覧系 API

api-contract §3.1 の6本。実装上の詳細:

1. **一覧**(`GET /works`): `Work::listable()` + `?genre={slug}` フィルタ + `orderByDesc('latest_published_at')` + `paginate(20)`。各行の `episode_count` / `cheer_count` は published のみ対象の集計(`withCount` + 制約付きリレーション)。N+1 禁止(genre / author は eager load)。
2. **目次**(`GET /works/{uuid}`): 解決は `Work::visible()->where('uuid',$uuid)->firstOrFail()`(404 秘匿が自然に落ちる)。**目次の合成アルゴリズム**:
   - published エピソードを `(chapter_id, position)` で取得(I3)、chapters を position 順で取得
   - 「章なし区画(chapter_id NULL、先頭)→ 各章」の順に配列合成。**空の章なし区画は要素ごと省略、空の章は `episodes: []` で出す**(api-contract §4.2)
3. **本文**(`GET /works/{w}/episodes/{e}`): 親子不整合(episode.work_id ≠ work.id)は 404。**prev/next アルゴリズム**: 目次と同じ読書順で published のみを平坦化した配列を作り、当該エピソードの前後を取る(端は null)。DB で完結させようとしない(2階層順序のSQL化は複雑化するだけ。目次サイズの配列で十分)。
4. レスポンスはすべて API Resource 経由(implementation-rules §5-2)。内部 id を含めないことをテストで検証(レスポンス JSON に `"id"` キーが存在しないこと)。

**完了条件**: **可視性マトリクス(state-machine §4)の全組み合わせ**(visibility 2 × status 3 × アクター: Guest/他人/本人 — ただし本人閲覧は Step 5 の管理系で担保)を Feature テストで網羅。prev/next が draft/scheduled を飛ばすテスト。ページ範囲外が空 data で 200。

## 7. Step 5: 作品管理 API

api-contract §3.4 の works 系 7本。実装上の詳細:

- **Policy**: `WorkPolicy@manage`(`$user->id === $work->user_id`)。管理系は全部これを通す。認可の失敗は、対象が public なら 403、private なら 404(AQ5。実装は「private は解決段階で 404 に落とす」= `/my/` 系は自分の作品しか解決しない、公開作品への他人の書き込みだけが Policy で 403 になる)。
- 作成(`POST /works`): visibility は強制 private、serialization_status は ongoing。入力は validation-spec §2 Work。
- `PATCH /works/{uuid}`: title / catchphrase / theme_color / synopsis / genre のみ受理(visibility 等が来たら 422 で拒否ではなく **単に無視しない — バリデーションで `prohibited`** とする)。
- `PUT /visibility` / `POST /complete` / `POST /reopen`: ドメインメソッド `$work->changeVisibility()` / `complete()` / `reopen()` 経由。
- 削除: `DELETE` 1文で FK カスケードに任せる(db-schema §6)。削除前に ISR 失効タグを算出しておく(Step 9 で発火を接続)。
- `GET /my/works` / `GET /my/works/{uuid}`: 全状態込み。管理用目次は §4.2 と同形+各話 `status` / `scheduled_at` 付き。

**完了条件**: U12〜U16 の正常系+ 他人の作品への操作(403/404 使い分け)+ completed 中の挙動は Step 7 でまとめて検証。

## 8. Step 6: 章管理と目次全体更新

- 章作成: 末尾 position(`MAX(position)+1`)。**Work 行を `lockForUpdate()` してから採番**(エピソード作成の採番も同じ。implementation-rules §3)。
- **章削除(前章統合・Q3)のアルゴリズム**(1トランザクション、Work ロック下):
  1. 統合先を決定: 削除章より position が小さい直近の章。なければ**章なし区画**
  2. 配下エピソードを相対順序を保って統合先の**末尾**に移動(chapter_id 更新+統合先区画の連番で position 振り直し)
  3. 章を削除し、章の position を詰めて振り直す
- **`PUT /works/{uuid}/structure`**(api-contract §4.4)のアルゴリズム(1トランザクション、Work ロック下):
  1. 検証: リクエスト内の episode uuid 集合 = 当該 Work の全エピソード uuid 集合(過不足・重複なし)、chapter uuid 集合 = 当該 Work の全章集合(**章の省略も不可**=章の並びも全体を送る)。違反は 422
  2. chapters の position を配列順で 1..N に更新
  3. 各エピソードの chapter_id(chapterless は NULL)と position(区画内配列順 1..N)を更新
  - 実装は「全行 UPDATE」でよい(案A。差分最適化を書かない)

**完了条件**: 並び替え・章跨ぎ移動・章なし区画への出し入れ・先頭章削除(章なし区画へ統合)・中間章削除(前章へ統合)・空章削除、structure の過不足/重複/他作品 uuid 混入の 422。並び順の検証は「目次 API の返す順序」で行う(実装内部の position 値に依存したアサーションを書かない)。

## 9. Step 7: エピソード管理と状態遷移

- **遷移メソッドは Episode モデルに実装**(implementation-rules §1・§2): `publish()` / `schedule(CarbonImmutable $at)` / `unschedule()` / `unpublish()`。各メソッドは現在状態を検査し、不正なら `DomainRuleViolation`(`invalid_status_transition`)。
- **ガードの所在**:
  - T1(作成): `CreateEpisode` UseCase が Work の completed を検査(`work_completed`)。**Q1 確定により、T2/T3 に completed ガードはない**(既存 draft の publish/schedule は completed でも可)
  - T9/T10(削除): draft / scheduled のみ。published は `episode_published` で 409
- `publish()`: status→published、`published_at = now()`(**毎回上書き・DQ4**)。同一トランザクションで `work->refreshLatestPublishedAt()`(下記)。
- `unpublish()`: status→draft。scheduled_at は触らない(元々 null)。同一トランザクションで latest 再計算。
- `schedule($at)`: 検証(未来・秒切り捨て・1年以内)は FormRequest(validation-spec)。draft からも scheduled からも可(T3/T6)。
- **`Work::refreshLatestPublishedAt()`**: `$this->latest_published_at = $this->episodes()->where('status', Published)->max('published_at');`(null 可)。publish 時は GREATEST 最適化でもよいが、**常に MAX 再計算の1実装に統一**してよい(単純さ優先。I5 が効く)。
- `PATCH /episodes/{uuid}`: title/body 更新+`character_count` 再計算(改行 LF を除いたコードポイント数。validation-spec §3)。published への PATCH が revise(T8。状態は変えない)。
- 作成時の所属: `chapter_uuid` 指定(当該 Work の章であること)または省略(章なし区画)。position は区画末尾採番(Work ロック下)。

**完了条件**: **遷移表 T1〜T10 の全行**(成功side +各ガード違反が正しい code)。completed の Work で「作成は 409 / 既存 draft の publish は成功」の対比テスト。published_at が再公開で更新されるテスト。latest_published_at が publish/unpublish で正しく動くテスト(複数話・最大値・0件で NULL)。

## 10. Step 8: 予約公開スケジューラ

- Artisan コマンド `episodes:publish-due`。`routes/console.php`(または Kernel)で `everyMinute()->withoutOverlapping()`。
- アルゴリズム(implementation-rules §3):
  1. `Episode::where('status', Scheduled)->where('scheduled_at', '<=', now())->pluck('id')`(I4)
  2. **1件ずつ独立トランザクション**: 行を `lockForUpdate()` で再取得 → status 再確認(競合で既に変わっていたら skip)→ `publish()` → イベント発行。1件の例外は捕捉してログし、残りを続行
- コンテナでのスケジューラ実行方式(`schedule:work` を別プロセスで起動する等)は既存のインフラ構成に合わせて選択し、compose / ECS タスク定義への追加を README に記す。

**完了条件**: `Carbon::setTestNow` で「時刻到来分だけ公開される」「published_at はスケジューラ実行時刻」「1件の失敗が他を巻き込まない」テスト。実時間 sleep 禁止。

## 11. Step 9: ISR 再検証発火

- **イベント→タグ対応**(isr-contract §3 が正)。イベントクラスは `app/Events/` に:
  `EpisodePublished` / `EpisodeUnpublished` / `EpisodeRevised` / `WorkStructureChanged`(structure・章作成/改名/削除)/ `WorkMetaUpdated` / `WorkVisibilityChanged` / `WorkSerializationChanged` / `WorkDeleted`
- 各 UseCase がトランザクション内で `event()`(1 UseCase 1 イベント)。Listener(`queue` 経由でなく同期)が **`RevalidateIsrTags` ジョブを `afterCommit()` でディスパッチ**する。
- `RevalidateIsrTags` ジョブ: `tries=3`、`backoff=[10, 60, 300]`。処理: `POST {ISR_REVALIDATE_URL}/api/revalidate`、ヘッダ `x-revalidate-secret: {ISR_REVALIDATE_SECRET}`、ボディ `{"tags": [...]}`。最終失敗は `failed()` でログのみ(公開処理と分離・IQ5)。
- env: `ISR_REVALIDATE_URL`(ローカル `http://web:3000`、本番 Service Connect 名)、`ISR_REVALIDATE_SECRET`(ローカル .env / 本番 Secrets Manager — Terraform 追加は Step 14)。
- Work 削除はイベントに uuid を**値で**持たせる(モデルは消えているため)。

**完了条件**: **発火表(isr-contract §3)の全行**を `Queue::fake` で検証 — 期待タグのジョブ投入、および「発火なし」行(draft 編集・T9/T10 削除・応援・コメント)でジョブが**投入されない**こと。ジョブ本体は HTTP fake で成功/リトライ/最終失敗(例外を外に出さない)をテスト。

## 12. Step 10: 応援・お気に入り・応援コメント

- 応援トグル(api-contract §3.3): PUT は INSERT の一意制約違反を握って 204(competing 連打対応。アプリで事前 SELECT しない)。DELETE は `delete()` の結果に関わらず 204。**対象エピソードは `Episode::visible()` で解決**(非可視は 404)。
- お気に入りも同形(Work 単位)。`GET /my/favorites` は `paginate(20)`、登録降順(I7)。
- コメント: 投稿(visible エピソードのみ)・一覧(公開系、`paginate(20)` 新しい順)・削除(`CheerCommentPolicy@delete`: 書き手本人 or エピソードの Work 所有者 — 不変条件7)。
- `GET /episodes/{uuid}/cheer`: `{"count": n, "cheered": bool}`(未認証は cheered: false)。

**完了条件**: 冪等性(2連打で 204/204、行は1つ)、非可視対象 404、コメント削除の二重権限(本人◯・作者◯・第三者 403)、応援・コメントで**再検証ジョブが飛ばない**こと(Step 9 の表と重複してよい)。

## 13. Step 11〜13: フロントエンド(Next.js)

### Step 11: スキャフォールドと公開閲覧ページ

- CLAUDE.md の手順で `frontend/` に scaffold(App Router / TS)。`next.config.ts`: `output: 'standalone'`、`rewrites` で `/api/*` と `/sanctum/*` → `http://nginx:80`。
- **fetch 基盤**: サーバー側は `process.env.INTERNAL_API_URL`(ローカル `http://nginx:80` / 本番 `http://api:80`)、クライアント側は同一オリジン `/api`。この出し分けを1つの API クライアントモジュールに集約。
- ルート(isr-contract §1): `/`・`/works/page/[page]`・`/genres/[slug]/page/[page]`・`/works/[workUuid]`・`/works/[workUuid]/episodes/[episodeUuid]`。
- ISR 設定: 各ページで `fetch(url, { next: { tags: [...], revalidate: N } })`(タグと秒数は isr-contract §1 の表)。`generateStaticParams` で API を叩かない(全ページ初回アクセス生成)。
- 表示要件: 一覧カード(タイトル・**テーマカラー文字色のキャッチコピー**・ジャンル・話数・応援数・最終公開日時)、目次(章見出し+話+公開日+文字数)、本文(prev/next ナビ)。デザインは簡素でよい(このプロジェクトの評価対象外)が、キャッチコピー×テーマカラーはカクヨム再現の要点なので必ず実装。

### Step 12: cacheHandler と再検証エンドポイント

- **cacheHandler**: `cacheHandler` 設定で Redis backend のカスタムハンドラを実装(`ioredis`)。キー prefix `isr:`、タグ→キーの逆引き(タグ manifest)も Redis に持つ。ローカルは compose の `redis`(**DB 番号を Laravel と分ける**: Laravel=0, ISR=1)、本番は ElastiCache(env `ISR_REDIS_URL`)。
- **再検証エンドポイント**: route handler `POST /internal/revalidate`(isr-contract §4 で確定。`/api/*` は Laravel への rewrite と衝突するため使わない)— `x-revalidate-secret` 検証(不一致 401)→ body の各タグに `revalidateTag()` → `{"revalidated": [...]}`。

**完了条件(E2E)**: ローカルで「エピソード publish → 数秒内に一覧・目次・本文ページに反映」「応援 → ページは変わらず、5分後の revalidate で数字だけ更新」を手動確認し、確認手順を `docs/design/verify-isr.md` に記録。

### Step 13: 認証・読者機能・管理画面

- 認証: `/sanctum/csrf-cookie` → login の順で呼ぶ薄い auth クライアント。`XSRF-TOKEN` cookie を `X-XSRF-TOKEN` ヘッダへ。
- CSR コンポーネント: 応援ボタン(トグル・楽観更新)、応援数、コメント欄(一覧+投稿+自分の削除)。本文ページの ISR HTML には含めない(枠だけ置いてクライアントフェッチ)。
- SSR ページ: `/favorites`、`/my/works`(一覧・作品編集・目次並び替え・エピソード編集・公開操作)。SSR の認証付き fetch は**受信 cookie を Laravel へ転送**(AQ2)。並び替え UI はドラッグ&ドロップでなくてよい(上下移動ボタン+章移動セレクトで可)— 保存は structure PUT 1発。
- 予約公開 UI: datetime-local 入力(分精度)。

**完了条件**: U1〜U28 の全ユースケースがブラウザから実行可能。

## 14. Step 14: 仕上げ

1. デモシーダー: 作者2名・作品4〜6(章あり/章なし/完結済/非公開を含む)・各話 published/draft/scheduled 混在・応援/コメント若干。ポートフォリオ閲覧者が最初に見る状態を作る。
2. Terraform: `ISR_REVALIDATE_SECRET` を Secrets Manager に追加し両サービスへ注入、スケジューラの実行方式を ECS 側に反映、CloudFront default ビヘイビアが CachingDisabled 相当であることを照合(isr-contract §8)。
3. CI/CD: バックエンドテスト+静的解析、フロントの build を既存 2 パイプラインに統合。
4. `docs/TODO.md` のフェーズ3該当項目を消し込み、README にローカル起動手順の差分を反映。

---

## 15. 実装時に迷いやすい点の先回り回答(FAQ)

- **「completed の Work で draft を publish していい?」** → 良い(Q1 確定)。禁止は新規作成(T1)のみ。
- **「published を直接 DELETE したい」** → 不可。409 `episode_published`。unpublish が先(ガイドは UI 文言で)。
- **「scheduled を DELETE したい」** → 可(T10・Q4 確定)。
- **「並び替えで position が飛び番になった」** → バグ。案A は常に 1..N の密な連番(全体書き換えの実装漏れ)。
- **「visibility を private にしたら episode.status も変える?」** → 変えない。可視性は合成で決まる(不変条件1)。
- **「応援したら ISR 失効させたくなった」** → させない(発火表が正。数字は時間ベース revalidate 任せ)。
- **「レスポンスに内部 id が混ざった」** → Resource を介していない箇所がある。`toArray()` 直返し禁止(implementation-rules §5)。
- **「テストで sleep したくなった」** → `Carbon::setTestNow`。
- **「テーマカラー54色が来ない」** → プレースホルダ(config)のまま進めてよい。値は後差し替え(Q8)。
- **「カクヨムの実物の上限文字数がわからない」** → validation-spec の運用値が正。勝手に実物を推測して変えない(§5 の確認リスト経由で更新)。
