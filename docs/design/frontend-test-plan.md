# フロントエンドテスト設計書

役割: `frontend/` のテスト戦略・テストケース・CI 統合の設計。**この文書を正として実装する**。
個別ケースは ID(FT-U* / FT-C* / FT-E*)で参照し、テストコード内のテスト名にも ID を含めること。

ステータス: **確定**(設計のみ。実装は未着手)
最終更新: 2026-07-07

関連文書: `isr-contract.md`(タグ・秒数・再検証の契約)/ `api-contract.md`(エラー契約)/
`use-cases.md`(U1〜U29)/ `verify-isr.md`(FT-E3 が自動化対象とする手動確認手順)

---

## 1. 目的とスコープ

1. **自作 cacheHandler(`cache-handler.mjs`)のロジック** — 本プロジェクトで最も凝った自作部分
   (タグ失効の tags-manifest 方式、Buffer 復元、Redis 障害時のベストエフォート)に自動テストがない。
2. **fetch 基盤(`lib/api.ts` / `lib/client-api.ts` / `lib/server-auth.ts`)の契約** — サーバー/クライアント
   出し分け、CSRF 転記、エラー変換。全ページがこの上に乗る。
3. **クライアントコンポーネントの対話ロジック** — 楽観更新とロールバック(CheerButton)、
   目次組み替えと structure PUT のペイロード形状(StructureEditor)など。
4. **「Laravel イベント → キュー → 再検証 → ページ反映」の一気通貫** — 

### テストしないもの(明示的な非対象)

- **Server Components(`app/**/page.tsx`)の単体レンダリング** — React 19 の async Server Component は
  jsdom + Testing Library で安定してテストできない(公式にも未サポート)。ページの振る舞いは
  E2E(L3)で実ブラウザ経由で担保する。
- **Next.js フレームワーク自体の挙動**(rewrites・ISR の再生成タイミング・standalone 出力)—
  フレームワークのテストはしない。自作部分(cacheHandler・タグ契約)との境界だけをテストする。
- **表示のみのコンポーネント**(`WorkCard` / `Toc` / `Nav` / `Pagination` / `WorksListView`)—
  分岐がほぼなく、E2E が実データで通過する。単体テストの費用対効果がない。
- **`lib/theme-colors.ts` / `lib/types.ts`** — 定数と型のみ。
- **カバレッジの数値目標** — 置かない。本設計のケース表(P0/P1)を網羅することが完了条件。

---

## 2. テスト構成(3層)と技術選定

| 層 | 対象 | ツール | 実行環境 | API/DB |
|---|---|---|---|---|
| L1 ユニット | `cache-handler.mjs`、`lib/*`、`app/internal/revalidate/route.ts` | Vitest | node(一部 jsdom) | 不要(fetch/redis をスタブ) |
| L2 コンポーネント | `components/*` のクライアントコンポーネント | Vitest + @testing-library/react + user-event | jsdom | 不要(`@/lib/client-api` を vi.mock) |
| L3 E2E | クリティカルユーザージャーニー + ISR 再検証連鎖 | Playwright(chromium のみ) | docker compose 実スタック | 実物(MySQL/Redis/Laravel/queue worker) |

### 採用理由と却下した代替案

- **Vitest(Jest でなく)**: Next.js 15 + React 19 + TS の組み合わせで設定が最小。ESM の
  `cache-handler.mjs` をトランスパイル設定なしでそのまま import できる。
- **L2 のモック境界は `@/lib/client-api` を `vi.mock`(MSW を却下)**: fetch 層(CSRF 転記・
  エラー変換)は L1 で一度だけ検証する。MSW で全コンポーネントテストに fetch 層を通すと
  同じロジックを何十回も再検証することになり、失敗時の切り分けも悪化する。境界を1つ上げ、
  コンポーネントは「client-api をどう呼び、結果で UI をどう変えるか」だけをテストする。
- **E2E はモックなしの実スタック(API モックを却下)**: L3 の目的はフロント・バック間の
  契約(認証 cookie、エラー形状、ISR 再検証)の実地検証であり、モックすると目的が消える。
  バックエンドは compose で既に丸ごと起動できる。
- **redis はモジュールモック(testcontainers を却下)**: L1 で実 Redis を立てるとユニット層が
  インフラ依存になる。`vi.mock("redis")` のフェイク(後述 §4.1)で JSON 直列化・タグ manifest・
  例外パスまで検証でき、実 Redis との結合は L3(compose の redis DB2)が担保する。

### 追加する devDependencies(`frontend/package.json`)

```
vitest / @vitejs/plugin-react / jsdom
@testing-library/react @testing-library/user-event @testing-library/jest-dom
@playwright/test
```

バージョンは実装時点の最新安定(目安: vitest 3.x、@testing-library/react 16.x 以上=React 19 対応、
@playwright/test 1.5x)。scripts に `"test": "vitest run"`, `"test:watch": "vitest"`,
`"test:e2e": "playwright test"` を追加する。

### ディレクトリ構成

```
frontend/
├── vitest.config.ts          # plugin-react, environment: jsdom(既定), setupFiles
├── tests/
│   ├── setup.ts              # @testing-library/jest-dom の register
│   ├── unit/
│   │   ├── cache-handler.test.mjs      # @vitest-environment node
│   │   ├── api.server.test.ts          # @vitest-environment node(window なし分岐)
│   │   ├── api.client.test.ts          # jsdom(window あり分岐)
│   │   ├── client-api.test.ts          # jsdom
│   │   ├── isr.test.ts
│   │   └── revalidate-route.test.ts    # @vitest-environment node
│   └── component/
│       ├── CheerButton.test.tsx
│       ├── FavoriteButton.test.tsx
│       ├── CommentSection.test.tsx
│       ├── EpisodeStatusActions.test.tsx
│       └── StructureEditor.test.tsx
├── e2e/
│   ├── playwright.config.ts  # baseURL: http://localhost:3000, chromium のみ
│   ├── helpers/api.ts        # API 直叩きのデータ準備ヘルパ(§6.2)
│   ├── public-reading.spec.ts
│   ├── reader-actions.spec.ts
│   ├── author-publish-isr.spec.ts
│   ├── auth-guard.spec.ts
│   └── structure-edit.spec.ts
```

`tsconfig.json` の paths(`@/`)を vitest.config.ts の `resolve.alias` にも通すこと。
`e2e/` は vitest の `include` から除外する(Playwright テストを vitest が拾うと落ちる)。

---

## 3. 実装上の共通注意(先に読むこと)

1. **モジュールレベル状態のリセット**: `cache-handler.mjs`(`client` / `connecting` /
   `memoryCache` / `memoryTags`)と `lib/api.ts`(`INTERNAL_API_URL` を import 時に読む)は
   モジュールスコープに状態を持つ。テストごとに `vi.resetModules()` + 動的 `import()` で
   新しいインスタンスを得ること。env は `vi.stubEnv` で import **前に**設定する。
2. **fetch のスタブ**: L1 では `vi.stubGlobal("fetch", vi.fn())`。afterEach で
   `vi.unstubAllGlobals()` / `vi.unstubAllEnvs()`。
3. **時刻**: cache-handler のタグ失効は `Date.now()` の大小比較(`>=`)なので、
   `vi.useFakeTimers({ now: ... })` で決定的にする。実時間 sleep 禁止。
4. **next/navigation**: `EpisodeStatusActions` / `StructureEditor` は `useRouter().refresh()` を
   呼ぶ。`vi.mock("next/navigation", ...)` で `{ useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }) }`
   を返す共通ヘルパを `tests/component/` に置いてよい。
5. **confirm / prompt / alert**: jsdom に実装がない。`vi.stubGlobal` で戻り値を制御する。
6. **楽観更新の観測**: 解決タイミングを制御できる deferred(`let resolve; new Promise(r => resolve = r)`)
   を clientFetch モックに返させ、**resolve 前**の DOM を assert する(FT-C1 系)。

---

## 4. L1: ユニットテスト

### 4.1 `cache-handler.mjs`(最優先・P0)

redis モックの設計: `vi.mock("redis")` で `createClient` が以下のフェイクを返す。

```
フェイク: { isReady: true, connect: async () => {}, on: () => {},
  get/set: Map ベース(set は (key, json, {EX}) を記録)、
  hmGet(key, fields): Map ベース、 hSet(key, obj): Map ベース }
```

フェイクは **JSON 文字列を格納する**(実 Redis と同じく直列化を通すことが Buffer 復元
テストの前提)。「redis error モード」ではすべてのメソッドが throw するフェイクに差し替える。

| ID | モード | ケース | 期待 |
|---|---|---|---|
| FT-U1 | in-memory(REDIS_URL なし) | `set(key, data)` → `get(key)` | value・tags が往復する。存在しないキーは `null` |
| FT-U2 | in-memory | `set` → `revalidateTag(tag)` → `get` | `null`(タグ失効)。エントリのタグと**無関係な**タグの revalidate では失効しない |
| FT-U3 | in-memory | `revalidateTag` → 時間を進めて `set` → `get` | エントリが返る(lastModified > 失効時刻)。**境界**: 同一ミリ秒(`>=`)は失効扱いであることを固定するテストを含める |
| FT-U4 | in-memory | `get(key, { softTags: [tag] })` + そのタグを revalidate 済み | `null`。implicit タグ(revalidatePath 経路)がエントリ自身の tags に無くても効く |
| FT-U5 | in-memory | `revalidateTag` に文字列単体 / 配列 / 空配列 / falsy 混在 | `[tags].flat().filter(Boolean)` の仕様どおり全形式を受ける。空なら no-op |
| FT-U6 | redis | `set` → `get` で **Buffer を含む value** が往復 | 返値の該当フィールドが `Buffer` インスタンスで、バイト列が等しい(reviver の検証) |
| FT-U7 | redis | `set` | キーが `next:cache:` prefix、`EX: 86400` が渡る(ElastiCache 溢れ防止 TTL の固定) |
| FT-U8 | redis | タグ失効が hmGet/hSet(`next:tags-manifest`)経由で FT-U2/U3 と同じ結果になる | in-memory と Redis で失効セマンティクスが一致 |
| FT-U9 | redis error | 全メソッドが throw する状態で `get` / `set` / `revalidateTag` | `get` は `null`(キャッシュミス扱い)、`set` / `revalidateTag` は**例外を外に漏らさない**(ベストエフォート契約) |
| FT-U10 | redis 接続失敗 | `connect()` が reject | 以後の `get` が in-memory ではなく `null` 側に落ち、プロセスが落ちない |

### 4.2 `lib/api.ts`

| ID | 環境 | ケース | 期待 |
|---|---|---|---|
| FT-U11 | node | `apiFetch("/works")` | fetch URL が `${INTERNAL_API_URL}/api/v1/works`(stubEnv した値)。env 未設定時は `http://nginx:80` |
| FT-U12 | jsdom | 同上 | URL が `/api/v1/works`(相対=同一オリジン、rewrites 前提) |
| FT-U13 | node | `{ tags, revalidate }` 指定 | fetch の第2引数 `next` に両方渡る。`{ cache: "no-store" }` 指定時は `cache` が渡り **`next` は undefined**(排他の固定) |
| FT-U14 | node | 非 2xx(JSON ボディあり/なし) | `ApiError` が throw され `status`・`body` を保持。ボディが JSON でなければ `body === null`(safeJson) |

### 4.3 `lib/client-api.ts`(jsdom)

| ID | ケース | 期待 |
|---|---|---|
| FT-U15 | `document.cookie` に URL エンコードされた `XSRF-TOKEN` がある状態で POST | `X-XSRF-TOKEN` ヘッダに **decodeURIComponent 済み**の値。`credentials: "same-origin"`、URL prefix `/api/v1` |
| FT-U16 | GET | `X-XSRF-TOKEN` を付けない。`Accept: application/json` は付く |
| FT-U17 | body ありの POST(Content-Type 未指定/指定済み) | 未指定なら `application/json` を自動付与、指定済みなら上書きしない |
| FT-U18 | レスポンス 204 | `undefined` が返る(json() を呼ばない) |
| FT-U19 | 非 2xx + `{message, errors}` ボディ | `ClientApiError` の `message` がボディの message、`body.errors` 保持(api-contract のエラー契約) |
| FT-U20 | `ensureCsrfCookie()` | `/sanctum/csrf-cookie` を `credentials: "same-origin"` で叩く |

### 4.4 `lib/isr.ts` / `app/internal/revalidate/route.ts`

| ID | ケース | 期待 |
|---|---|---|
| FT-U21 | isr.ts の定数・タグ形式 | `REVALIDATE_LIST_SECONDS === 300` / `REVALIDATE_EPISODE_SECONDS === 86400` / `workTag(x) === "work:x"` 等。**isr-contract §1/§2 との契約固定**(Laravel 側 `RevalidateIsrTags` が同じ文字列を生成するため、変えたら両方壊すべき値) |
| FT-U22 | revalidate route: シークレットなし / 不一致 | 401、`revalidateTag` 未呼び出し(`vi.mock("next/cache")`) |
| FT-U23 | 正しいシークレット + `{tags: ["works-list", "work:x"]}` | タグごとに `revalidateTag` が呼ばれ、レスポンス `{revalidated: [...]}` |
| FT-U24 | 不正ボディ(JSON でない / tags が配列でない / 非文字列混入) | 500 にせず 200 `{revalidated: []}`(非文字列は除外)。※ Laravel 側ジョブがリトライ3回で叩く相手なので「壊れた入力で 5xx を返さない」ことが重要 |

環境変数は `vi.stubEnv("ISR_REVALIDATE_SECRET", ...)`。`NextRequest` は `next/server` から
生成する(node 環境で動作する)。

---

## 5. L2: コンポーネントテスト

共通: `vi.mock("@/lib/client-api")`(`clientFetch` / `ClientApiError` は実クラスを re-export)。
`ClientApiError` は instanceof 判定に使われるため**実物を使う**こと(モックで別クラスにすると
401 分岐が通らない)。

### 5.1 `CheerButton`(P0 — 楽観更新の代表例)

| ID | ケース | 期待 |
|---|---|---|
| FT-C1 | 初期表示 | mount 直後は `null` を返し何も描画しない → GET `/episodes/{uuid}/cheer` 解決後に `☆ 応援する(N)` と `aria-pressed=false` |
| FT-C2 | 初期 GET が失敗(未ログイン等) | `{count: 0, cheered: false}` として描画(クラッシュしない) |
| FT-C3 | クリック(deferred で PUT を保留) | **解決前に** count+1・`★ 応援済み`・`aria-pressed=true`・disabled(楽観更新)。PUT は `/episodes/{uuid}/cheer` |
| FT-C4 | PUT が reject(500 等) | 表示が元の状態にロールバックされる |
| FT-C5 | PUT が 401 で reject | ロールバック + `alert` に「ログイン」を含む文言(`vi.stubGlobal("alert")`) |
| FT-C6 | 応援済み状態でクリック | DELETE が飛び、count−1・`☆` に切り替わる(トグル、U6) |

### 5.2 `CommentSection`(P0)

| ID | ケース | 期待 |
|---|---|---|
| FT-C7 | 未ログイン(`/auth/me` reject) | 投稿フォームが**出ない**。コメント一覧は表示される |
| FT-C8 | ログイン済み + コメント 0 件 | フォーム表示 + 「まだコメントがありません。」 |
| FT-C9 | 投稿成功 | POST `/episodes/{uuid}/comments` に `{body}`、textarea クリア、一覧 reload(GET 再発行) |
| FT-C10 | 投稿 401 / その他エラー | それぞれ「ログインしてください」系 /「投稿に失敗しました。」を表示。フォームは残る |
| FT-C11 | 削除ボタンの表示制御 | 自分のコメント、または `me.uuid === workAuthorUuid`(作品作者)のときのみ削除ボタンが出る(不変条件7 の見た目側)。confirm 承諾で DELETE `/comments/{uuid}` → reload |
| FT-C12 | 空・空白のみの body で submit | POST が**呼ばれない** |

### 5.3 `EpisodeStatusActions`(P0 — 状態遷移 UI)

| ID | ケース | 期待 |
|---|---|---|
| FT-C13 | status 別のボタン表示 | draft: 公開する/予約する/削除。scheduled: 予約日時を変更/予約を取り消す/削除。published: 取り下げるのみ(**削除ボタンなし** = T9/T10 のガードの見た目側) |
| FT-C14 | 公開する | POST `/episodes/{uuid}/publish` → `router.refresh()` |
| FT-C15 | 予約フロー | 「予約する」→ datetime-local 表示。値が空なら確定ボタン disabled。`2026-07-08T21:00` 入力で確定 → POST `/episodes/{uuid}/schedule` に `{scheduled_at: "2026-07-08 21:00:00"}`(**T→スペース+秒付与の変換を固定**。validation-spec の分精度契約) |
| FT-C16 | 遷移 API が `ClientApiError`(409 ドメインガード) | エラーメッセージ表示、refresh されない |
| FT-C17 | 削除 | confirm 承諾で DELETE、拒否で何も起きない |

### 5.4 `StructureEditor`(P0 — structure PUT の契約)

初期 props の `toc` は「章なし区画(chapter: null)先頭 + 章2つ」「章なし区画なし」の2系列を用意。

| ID | ケース | 期待 |
|---|---|---|
| FT-C18 | 初期描画 | 章なし区画が先頭に常に表示(toc に無くても空区画として出る)。各エピソードに status ラベル(公開中/予約中/下書き) |
| FT-C19 | エピソード ↑↓ | 区画内で入れ替わる。端(先頭で↑、末尾で↓)は no-op。操作後に「並び替えを保存」バーが出現(dirty) |
| FT-C20 | 「章を移動…」select | 対象エピソードが移動先区画の**末尾**に付く。現在の区画は選択肢に出ない |
| FT-C21 | 章の ↑↓ | 章同士のみ入れ替わる。**章なし区画(index 0)は常に先頭固定**(章の↑で index 0 と入れ替わらない) |
| FT-C22 | 保存 | PUT `/works/{uuid}/structure` のボディが `{chapterless: [uuid...], chapters: [{uuid, episodes: [uuid...]}]}` で、**画面の並びどおりの順序**(api-contract の structure 契約の固定)。成功で dirty 解除・保存バー消滅・refresh |
| FT-C23 | 保存失敗(`ClientApiError`) | エラー表示、dirty のまま(再保存できる) |
| FT-C24 | 章の追加/改名/削除 | それぞれ POST `/works/{uuid}/chapters` / PATCH `/chapters/{uuid}`(prompt 値) / DELETE(confirm 承諾時)→ refresh。空タイトルでは追加ボタン disabled |

### 5.5 `FavoriteButton`(P1)

FT-C25: CheerButton と同型(トグル・401 alert)。実装を読み、楽観更新でなければ
「解決後に表示が切り替わる」ことのみ assert する(実装に合わせる。書き換えない)。

`AuthForm` / `WorkForm` / 各作成フォームは L2 では扱わない(バリデーションはサーバー側が正で、
422 表示の分岐は FT-C10 と同型。E2E の登録・作品作成フローが実物で通す)。

---

## 6. L3: E2E(Playwright)

### 6.1 実行前提

- スタック: `docker compose up -d`(db/redis/app/nginx) + `--profile frontend up -d web` +
  **キューワーカー**。compose に queue worker サービスがないため、本設計の一部として
  `compose.yaml` に追加する:

  ```yaml
  # app と同イメージ・同ボリューム構成で command: php artisan queue:work --tries=3
  # profiles: [worker]。FT-E3(ISR 反映)の前提。verify-isr.md の手動起動手順も置き換える
  queue-worker:
    ...(app サービスの定義を踏襲)
  ```

- 事前に `php artisan migrate --seed`(genres マスタ)。**DemoSeeder には依存しない**。
- Next.js は compose の dev モードで走らせる(§6.4 の割り切り参照)。

### 6.2 データ戦略(テスト間独立)

- 各テストは Playwright の `request` コンテキストで **API を直接叩いて自前のデータを作る**
  (`e2e/helpers/api.ts`)。ユーザー名・作品タイトルに `Date.now()` 等のユニーク接尾辞を付け、
  DB リセット不要・並列実行安全・再実行安全にする。
- ヘルパは Sanctum SPA の手順を踏む: GET `/sanctum/csrf-cookie` → cookie jar の `XSRF-TOKEN` を
  `X-XSRF-TOKEN` ヘッダへ転記して POST(`Referer: http://localhost:3000/` 必須)。
  ブラウザ側にログイン状態が要るテストは、UI からログインするか `context.addCookies` で
  API ログイン済み jar を注入する(実装しやすい方でよいが、**FT-E2 の登録→ログインは必ず UI 経由**)。
- **ISR キャッシュ汚染対策**: 公開ページを assert するテストは必ず**そのテストが作った新規作品**
  のページを見る(uuid が新しい=キャッシュ未生成ページなので、前回実行の stale キャッシュを
  踏まない)。トップ一覧(`/`)の内容 assert は「自作品のタイトルが**いずれ現れる**」形にし、
  他テストのデータが混在しても通る述語にする。

### 6.3 シナリオ

| ID | ファイル | シナリオ | 検証内容 |
|---|---|---|---|
| FT-E1 | public-reading.spec.ts | 【ゲスト閲覧 U1〜U5】API で作者+公開作品(章2・公開エピソード3、うち1つは draft)を準備 → `/works/{uuid}` 目次に published のみ表示(draft が**出ない**) → エピソードページで本文表示 → 「次の話」で移動(draft を**飛ばして** published に着地) → ジャンルページ・トップに作品が現れる | 可視性マトリクスと prev/next の published-only 契約が実ページに出ること |
| FT-E2 | reader-actions.spec.ts | 【読者 U6〜U11】UI で新規登録 → 公開エピソードページで応援(カウント+1、リロード後も応援済み維持) → 再クリックで取り消し → コメント投稿(一覧に出る) → 自分のコメント削除 → お気に入り登録 → `/favorites` に作品が出る → 解除で消える | 認証 cookie・CSRF・トグル永続化の一気通貫 |
| FT-E3 | author-publish-isr.spec.ts | 【ISR 再検証 = verify-isr.md 確認1 の自動化】API で作者+公開作品+published 1話を準備 → ゲストで `/works/{uuid}` を開き**キャッシュを生成**(1話のみの目次を確認) → API で draft 2話目を作成し publish → `expect.poll`(reload しつつ最大 30s)で目次に2話目が現れる → 同様に unpublish で消えることも確認 | イベント→キュー→`/internal/revalidate`→cacheHandler(実 Redis DB2)→再生成の全連鎖。**queue-worker 停止中は必ず失敗する**こと自体が検知価値 |
| FT-E4 | auth-guard.spec.ts | 【認可ガード】未ログインで `/my/works` → `/login` へリダイレクト。ログイン後は表示。**他人の**作品の管理ページ `/my/works/{uuid}` を開く → 404/エラー表示(所有者スコープ) | SSR 側 authFetch の 401/403 ハンドリングが画面に出ること |
| FT-E5 (P1) | structure-edit.spec.ts | 【目次編集 U17〜U19/U26】作者でログイン → 管理画面で章追加 → エピソードを章へ移動 → 並び替え → 保存 → **公開側の目次**が新しい順序で表示される(新規作品なので ISR キャッシュは初回生成) | StructureEditor → structure PUT → 公開目次の順序反映 |

flaky 対策: FT-E3 の反映待ちは固定 sleep 禁止、`expect.poll` / リトライ付き reload で行う。
タイムアウトはキュー処理(実測 数百 ms)+ page 再生成を見込み 30s。`trace: "retain-on-failure"`。

### 6.4 割り切り(記録)

- **dev モードで E2E を走らせる**: 本番は standalone 出力だが、cacheHandler は dev でも有効
  (verify-isr.md で実機確認済み)であり、compose に prod 相当の frontend サービスがない。
  prod イメージでの E2E は deploy パイプライン整備時の将来課題として持ち越す。
- **予約公開(U22/U29)の E2E はしない**: スケジューラ tick はバックエンドの Pest で担保済み。
  E2E で「毎分」を待つのは遅くて flaky なだけで増分価値がない。
- **応援数が ISR ページに約5分遅れで反映される仕様(isr-contract §3)の実時間確認はしない**:
  「応援で再検証ジョブが飛ばない」ことは Pest 側で assert 済み。

---

## 7. CI 統合(`.github/workflows/ci.yml` の変更設計)

1. **既存 `frontend` ジョブに vitest を追加**(安い順の原則を踏襲):
   `tsc --noEmit` → **`npm run test`(vitest)** → `next build`。
2. **新規 `e2e` ジョブ**(quality / frontend と並列):

   ```
   - checkout
   - cp .env.example .env
   - docker compose build(nginx/app/web。GHA の buildx キャッシュ type=gha を使う)
   - docker compose up -d && --profile frontend up -d web && --profile worker up -d queue-worker
   - compose run --rm app: composer install → key:generate → migrate --seed
   - web の疎通待ち(curl リトライで http://localhost:3000 と :8080/api/health)
   - frontend で npm ci && npx playwright install --with-deps chromium
   - npm run test:e2e
   - 失敗時: playwright-report / trace を actions/upload-artifact
   ```

   ※ Playwright はホスト側(ubuntu-latest)で実行し、localhost 公開ポート越しにスタックを叩く。
   コンテナ化した Playwright より構成が単純で、compose のポート公開が実際に検証される。
3. E2E は所要 5〜8 分を見込む。PR 必須チェックには含めるが、`concurrency` で同一 ref の
   古い実行をキャンセルする(既存ワークフローの方針に合わせる)。

---

## 8. 実装順序と完了条件

| 優先 | 内容 | 完了条件 |
|---|---|---|
| P0-1 | Vitest 基盤 + §4 ユニット(FT-U1〜U24) | `npm run test` が全件 green。cache-handler の in-memory / redis / error 3 モードを網羅 |
| P0-2 | §5 コンポーネント(FT-C1〜C24) | 同上。`ClientApiError` は実クラス使用(§5 冒頭) |
| P0-3 | compose への queue-worker 追加 + Playwright 基盤 + FT-E1〜E4 | ローカルで `npm run test:e2e` が clean な `docker compose up` から2回連続 green(再実行安全の確認) |
| P0-4 | CI 統合(§7) | push で 3 ジョブ全て green。E2E 失敗時に trace が artifact に残る |
| P1 | FT-C25 / FT-E5 | — |

実装時の禁止事項: テストを通すためのプロダクションコード変更は、バグ発見時を除き行わない
(発見したら修正はテストと別コミットに分け、コミットメッセージでケース ID を引く)。
テスト名は `test("FT-U1: ...")` の形式で ID を先頭に付ける。完了後、ルート README.md の
「品質担保」節にフロントエンドテストの行を追記すること。
