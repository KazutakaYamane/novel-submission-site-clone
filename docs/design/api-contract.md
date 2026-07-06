# 論理設計: API契約(たたき台)

入力: `use-cases.md`(U1〜U29) / `state-machine.md`(遷移 T1〜T10・可視性) / `db-schema.md`(公開 ID = uuid)。
役割: Laravel(JSON API)と Next.js の**契約書**。フロント実装はこの文書だけを見て書ける状態を目指す。

ステータス: **確定**(2026-07-06 AQ1〜AQ5 全承認。記録は §6)
最終更新: 2026-07-06

---

## 1. 全体方針

| 項目 | 方針 | 備考 |
|---|---|---|
| ベースパス | `/api/v1` | CloudFront/ALB の `/api/*` ルーティング配下。v1 を切っておく(破壊的変更時の教科書対応) |
| リソース ID | **公開 uuid のみ**を URL・レスポンスに使う。内部 ID(auto increment)は一切出さない | `db-schema.md` §1.1 の規律。implementation-rules で強制 |
| JSON 命名 | snake_case(DB カラムと一致させ写像を減らす) | |
| 日時形式 | ISO 8601 +09:00 固定(例: `2026-07-06T21:00:00+09:00`) | 全層 JST 統一(DQ6)。オフセットは明示する |
| URL ネスト | 閲覧系はページ URL と 1:1 の2段ネスト(`/works/{uuid}/episodes/{uuid}`)。操作系は最短フラット(`/episodes/{uuid}/publish`) | 親子不整合(work 配下にない episode)は 404 |
| 認証 | **Sanctum SPA cookie 方式**(セッションは構築済み Redis)。AQ2 | 事前に `GET /sanctum/csrf-cookie`、以降 CSRF トークンヘッダ必須。Next.js からは同一オリジン(rewrites / CloudFront 同一ドメイン)なので CORS 不要 |
| 認可 | 管理系は「対象 Work の所有者」を Laravel Policy で判定 | 可視性マトリクス(state-machine §4)が公開系の正規表 |
| 非可視リソース | **一律 404**(存在秘匿)。private 作品・draft/scheduled エピソードは他者には「存在しない」 | 403 を返すと存在が漏れる。AQ5 |
| ページネーション | **page ベース**(`?page=N`、Laravel paginator 標準)。AQ3 | 一覧は ISR ページ(`/works?page=2` が SEO 対象)なので offset ベースが自然 |
| レート制限・API バージョンヘッダ等 | 意図的省略(ADR 記録) | |

## 2. 状態遷移の API 表現(AQ1)

**方針: ガード付き遷移はアクション型 POST、ガードなしの値スイッチは PUT。**

- Episode の遷移(T2/T3/T5/T7)= `POST /episodes/{uuid}/publish` 等。理由: 遷移はガード・副作用(published_at 設定、latest_published_at 更新、ISR 再検証)を持つ**ドメインイベント**であり、`PATCH {status: "published"}` の形にすると「statusという値の更新」に見えてガードの所在が曖昧になる。ユビキタス言語の動詞(publish/schedule/unschedule/unpublish)が URL にそのまま現れる。
- Work の連載ステータス(complete/reopen)も同じ理由でアクション型 POST。
- Work の visibility は**ガードのない2値スイッチ**なので `PUT /works/{uuid}/visibility`(値の置き換え)。エピソードの publish と語彙が衝突しない利点もある。
- 却下した代案: 全部 `PATCH` によるリソース状態更新(REST 純粋主義)。遷移表 T1〜T10 との対応が API 面から消えるため不採用。

## 3. エンドポイント一覧

### 3.1 公開閲覧系(認証不要・ISR/CSR のデータソース)

| Method / Path | 内容 | UC | 消費元 |
|---|---|---|---|
| GET `/api/v1/genres` | ジャンル一覧(固定マスタ) | U2 | ISR |
| GET `/api/v1/works?genre={slug}&page={n}` | 作品一覧(latest_published_at 降順、public かつ published 1件以上) | U1/U2 | ISR |
| GET `/api/v1/works/{workUuid}` | 作品詳細+目次(published のみ、読書順) | U3 | ISR |
| GET `/api/v1/works/{workUuid}/episodes/{episodeUuid}` | エピソード本文+prev/next | U4/U5 | ISR |
| GET `/api/v1/episodes/{episodeUuid}/comments?page={n}` | 応援コメント一覧(新しい順) | U11 | **CSR** |
| GET `/api/v1/episodes/{episodeUuid}/cheer` | 応援数+(認証時)自分が応援済みか | U6 | **CSR** |

### 3.2 認証

| Method / Path | 内容 | 備考 |
|---|---|---|
| POST `/api/v1/auth/register` | 登録(name / email / password) | メール確認なし(意図的省略) |
| POST `/api/v1/auth/login` | ログイン | セッション発行(cookie) |
| POST `/api/v1/auth/logout` | ログアウト | |
| GET `/api/v1/auth/me` | 自分の情報(uuid / name / email) | 未認証は 401 |

### 3.3 読者操作(要認証)

| Method / Path | 内容 | UC | 備考 |
|---|---|---|---|
| PUT `/api/v1/episodes/{uuid}/cheer` | 応援する | U6 | **冪等**: 応援済みなら何もせず 204 |
| DELETE `/api/v1/episodes/{uuid}/cheer` | 応援を取り消す | U6 | **冪等**: 未応援でも 204 |
| PUT `/api/v1/works/{uuid}/favorite` | お気に入り登録 | U7 | 冪等 |
| DELETE `/api/v1/works/{uuid}/favorite` | お気に入り解除 | U7 | 冪等 |
| GET `/api/v1/my/favorites?page={n}` | お気に入り一覧(登録順降順) | U8 | SSR ページのデータソース |
| POST `/api/v1/episodes/{uuid}/comments` | 応援コメント投稿 | U9 | 対象は可視エピソードのみ(非可視は 404) |
| DELETE `/api/v1/comments/{uuid}` | コメント削除 | U10/U28 | 書き手本人 or エピソードの Author(不変条件7) |

トグル系を PUT/DELETE の冪等ペアにするのは、CSR の連打・リトライで安全にするため(POST だと二重送信が 409 になる)。

### 3.4 作品・章・構成の管理(要認証+所有。閲覧は draft/scheduled 込み)

| Method / Path | 内容 | UC / 遷移 | 備考 |
|---|---|---|---|
| GET `/api/v1/my/works?page={n}` | 自分の作品一覧(全 visibility) | U27 | SSR |
| POST `/api/v1/works` | 作品作成 | U12 | 作成直後は visibility = private |
| GET `/api/v1/my/works/{uuid}` | 管理用詳細+**全状態込み目次**(draft/scheduled 含む) | U27 | |
| PATCH `/api/v1/works/{uuid}` | メタ編集(title / catchphrase / theme_color / synopsis / genre) | U13 | visibility・serialization_status は含まない(§2) |
| PUT `/api/v1/works/{uuid}/visibility` | `{"visibility": "public"\|"private"}` | U14 | ガードなしスイッチ |
| POST `/api/v1/works/{uuid}/complete` | 完結させる | U15 | |
| POST `/api/v1/works/{uuid}/reopen` | 連載再開 | U15 | |
| DELETE `/api/v1/works/{uuid}` | 作品削除(カスケード) | U16 | |
| POST `/api/v1/works/{uuid}/chapters` | 章作成(`{title}`。末尾に追加) | U17 | |
| PATCH `/api/v1/chapters/{uuid}` | 章名変更 | U17 | |
| DELETE `/api/v1/chapters/{uuid}` | 章削除(配下エピソードは前章末尾へ自動統合、先頭章なら章なし区画へ = Q3) | U19 | |
| PUT `/api/v1/works/{uuid}/structure` | **目次全体更新**(章の並び・エピソードの並び・所属を一括置換) | U18/U26 | AQ4。§4.4 に形状 |

### 3.5 エピソードの管理(要認証+所有)

| Method / Path | 内容 | 遷移 | 備考 |
|---|---|---|---|
| POST `/api/v1/works/{uuid}/episodes` | 作成(`{title, body, chapter_uuid?}`。区画末尾に追加) | T1 | completed の Work は 409(唯一のガード、Q1) |
| GET `/api/v1/my/episodes/{uuid}` | 編集用取得(全状態) | — | |
| PATCH `/api/v1/episodes/{uuid}` | title / body の更新。published に対しては revise | T8 | draft/scheduled の編集と published の改稿を同一エンドポイントで扱う(状態は変えない) |
| POST `/api/v1/episodes/{uuid}/publish` | 公開 | T2 | published_at = now(毎回更新・DQ4) |
| POST `/api/v1/episodes/{uuid}/schedule` | `{"scheduled_at": "..."}` 予約/予約変更 | T3/T6 | draft・scheduled 双方から受け付ける(T6 = 再実行)。過去時刻は 422 |
| POST `/api/v1/episodes/{uuid}/unschedule` | 予約取消 | T5 | |
| POST `/api/v1/episodes/{uuid}/unpublish` | 取り下げ | T7 | latest_published_at 再計算 |
| DELETE `/api/v1/episodes/{uuid}` | 削除 | T9/T10 | draft・scheduled のみ。published は 409(code: `episode_published`) |

U29(予約時刻到来)はスケジューラ内部処理であり API を持たない。

## 4. 主要なレスポンス/リクエスト形状

### 4.1 作品一覧(GET /works)

```json
{
  "data": [
    {
      "uuid": "...", "title": "...", "catchphrase": "...", "theme_color": "...",
      "genre": {"slug": "fantasy", "name": "ファンタジー"},
      "serialization_status": "ongoing",
      "latest_published_at": "2026-07-06T21:00:00+09:00",
      "episode_count": 42, "cheer_count": 128
    }
  ],
  "meta": {"current_page": 1, "last_page": 5, "per_page": 20, "total": 90}
}
```

episode_count / cheer_count は published のみを数え、ISR 再生成時に集計(DQ7)。

### 4.2 作品詳細+目次(GET /works/{uuid})

```json
{
  "work": { "uuid": "...", "title": "...", "catchphrase": "...", "theme_color": "...",
            "synopsis": "...", "genre": {...}, "serialization_status": "ongoing",
            "author": {"uuid": "...", "name": "..."} },
  "toc": [
    { "chapter": null,
      "episodes": [ {"uuid": "...", "title": "...", "published_at": "...", "character_count": 3200, "cheer_count": 10} ] },
    { "chapter": {"uuid": "...", "title": "第一章"},
      "episodes": [ ... ] }
  ]
}
```

`toc` は読書順(章なし区画が先頭、以下 Chapter の position 順)。published のみ。章なし区画が空なら `chapter: null` の要素自体を返さない。管理用(GET /my/works/{uuid})は同形で全状態+各話 `status`・`scheduled_at` 付き。

### 4.3 エピソード本文(GET /works/{w}/episodes/{e})

```json
{
  "episode": {"uuid": "...", "title": "...", "body": "...", "published_at": "...", "character_count": 3200},
  "work": {"uuid": "...", "title": "...", "theme_color": "...", "author": {...}},
  "prev": {"uuid": "...", "title": "..."} ,
  "next": null
}
```

prev/next は読書順で published のみをたどった隣(なければ null)。

### 4.4 目次全体更新(PUT /works/{uuid}/structure)

案A(区画全体書き換え)と 1:1 のリクエスト。**その作品の全エピソードを網羅した最終形**を送る:

```json
{
  "chapterless": ["ep-uuid-1", "ep-uuid-2"],
  "chapters": [
    {"uuid": "ch-uuid-1", "episodes": ["ep-uuid-3"]},
    {"uuid": "ch-uuid-2", "episodes": []}
  ]
}
```

- 配列順がそのまま position(1..N)。章の並び・章内の並び・章間移動・章なし区画への出し入れをこの1リクエストで表現する。
- バリデーション: 全エピソード uuid の**過不足なし・重複なし**、全 chapter uuid が当該 Work のもの。違反は 422。
- 1トランザクションで全 position を振り直す(db-schema §2)。

## 5. エラー契約

共通エンベロープ: `{"message": "...", "code": "...", "errors": {...}}`(code は機械可読、errors は 422 のみ)。

| ステータス | 用途 | code の例 |
|---|---|---|
| 401 | 未認証(要認証エンドポイント) | `unauthenticated` |
| 404 | 非存在 **+ 非可視**(存在秘匿。private 作品・draft/scheduled を他者が参照、親子不整合) | `not_found` |
| 409 | ドメインガード違反(遷移表・不変条件による拒否) | `work_completed`(completed への T1)/ `episode_published`(published の削除)/ `invalid_status_transition`(published への publish 等) |
| 422 | 入力バリデーション違反(文字数超過、過去の scheduled_at、structure の過不足、プリセット外の theme_color) | `validation_failed` + errors バッグ(Laravel 標準形) |
| 403 | 認証済みだが所有者でない管理操作 | `forbidden`(※ 404 秘匿と使い分け: 公開されている作品への他人の管理操作のみ 403、非公開リソースは 404。AQ5) |

## 6. 判断の記録(AQ1〜AQ5・全承認 2026-07-06)

- **AQ1** → 承認: 状態遷移の表現 = **ガード付き遷移はアクション型 POST、値スイッチは PUT**(§2)。
- **AQ2** → 承認: 認証 = **Sanctum SPA cookie 方式**(セッション Redis、CSRF あり、トークン発行なし)。SSR の認証付き fetch は Next.js サーバが受信 cookie を Laravel へ転送して実現(個人化ページは SSR の確定と整合)。
- **AQ3** → 承認: ページネーション = **page ベース**(offset)。無限スクロールにしたくなった場合も page ベースで実装可能。
- **AQ4** → 承認: 並び替え・移動 API を **PUT /works/{uuid}/structure(目次全体置換)1本に集約**(§4.4)。個別 move/reorder エンドポイント群は作らない。
- **AQ5** → 承認: 非可視リソースへのアクセスは一律 **404(存在秘匿)**、公開リソースへの非所有者の管理操作のみ 403。

## 7. 次工程への引き継ぎ

- 各フィールドの上限値・必須/任意 → `validation-spec.md`
- ISR が叩くエンドポイント(§3.1)とキャッシュタグの対応 → `isr-contract.md`
- Policy・FormRequest・アクション型 POST の実装位置、内部 ID 非公開の強制 → `implementation-rules.md`
