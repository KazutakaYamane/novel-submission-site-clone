# 論理設計: DBスキーマ(たたき台)

入力: `domain-model.md`(概念モデル・不変条件) / `state-machine.md`(遷移・ISR トリガー) / `use-cases.md`(クエリの源泉)。
対象 DB: MySQL 8.0(utf8mb4 / utf8mb4_unicode_ci はサーバレベル設定済み)。マイグレーションは Laravel で書く前提。

ステータス: **確定**(2026-07-06 DQ1〜DQ7 全回答を反映。記録は §7)
最終更新: 2026-07-06

---

## 1. 全体の設計方針

| 項目 | 方針 | 根拠 / 備考 |
|---|---|---|
| 主キー | **ハイブリッド方式(2026-07-06 確定・DQ3)**: PK は BIGINT UNSIGNED AUTO_INCREMENT(内部専用)、URL/API に出るテーブルには別途 uuid カラム(CHAR(36), UNIQUE, UUIDv7 をアプリ側生成)を**公開 ID** として持つ。genres のみ固定マスタとして TINYINT | 比較は §1.1。公開 ID が要るのは users / works / chapters / episodes / cheer_comments のみ。cheers / favorites は「ユーザー×対象」で特定できるため公開 ID 不要 |
| enum の表現 | **VARCHAR + PHP の backed enum**(casts で対応)。MySQL の ENUM 型・TINYINT は使わない | ENUM 型は値追加が ALTER になる。TINYINT は DB を直接見たとき不可読。値の整合は CHECK 制約(下記)+アプリで担保 |
| CHECK 制約 | **張る**(MySQL 8.0.16+ で実際に強制される): enum 各カラムの値域、`(status='scheduled') = (scheduled_at IS NOT NULL)`(不変条件4) | ポートフォリオとして「不変条件を DB 層でも表明する」姿勢を見せる。アプリ層の強制(implementation-rules)の安全網 |
| 日時型 | **DATETIME**(TIMESTAMP は使わない: 2038年問題・暗黙のTZ変換を避ける)。タイムゾーンは**全層 Asia/Tokyo に統一**(DQ6 確定: Laravel config・php.ini・DATETIME 保存値・予約公開 UI すべて JST) | 予約公開の比較(`scheduled_at <= now`)が単一 TZ で閉じる。実サービスなら UTC 保存が定石である旨は ADR に記録 |
| 削除 | **物理削除のみ。ソフトデリートは採用しない** | 復元・コンプライアンス要件は実サービスでは要検討である旨を ADR に意図的省略として記録 |
| 話数番号 | カラムとして**持たない**(概念設計確定: position のみ) | |

### 1.1 ID 戦略の比較(2026-07-06 確定・ADR-BE 題材)

**採用: ハイブリッド方式(BIGINT AUTO_INCREMENT 内部 PK + UUIDv7 公開 ID)。**

| 観点 | UUIDv7 単一 PK | ハイブリッド(採用) |
|---|---|---|
| PK・FK・索引サイズ | CHAR(36) = 36B。InnoDB は全セカンダリ索引のリーフに PK を埋め込むため、索引・FK カラム全体に 36B が波及 | BIGINT = 8B で最小。クラスタ索引・セカンダリ索引・全 FK が軽い |
| JOIN・比較速度 | 文字列比較 | 整数比較(最速) |
| 挿入局所性 | v7 なら良好 | 厳密単調で最良 |
| ID の種類 | 1種類(単純) | 2種類。外部参照は `WHERE uuid=?`(UNIQUE 索引+1ルックアップ)。**内部 ID を API・URL・ログに漏らさない規律**が必要(implementation-rules で強制) |
| 公開面の安全 | 推測不能 | uuid のみ公開するので同等(連番の事業指標漏洩=総数・投稿ペース推定も防げる) |
| 分散・シャーディング | グローバル一意で強い | AUTO_INCREMENT はインスタンス固有で弱い |

決め手: **DB を分割しない前提**では UUID 単一 PK の唯一の構造的優位(グローバル一意)が使われず、サイズ・速度の不利だけが残る。特に cheers のような「小さい行×大量」テーブルの FK が 36B になるのは無駄が大きい。
公開 uuid も **UUIDv7** とする(PK でなくても UNIQUE 索引への挿入局所性に効く。生成時刻の漏洩は created_at を画面表示するため実害なし)。

---

## 2. position の表現戦略(最大の判断・DQ1)

並び替え・章間移動・章削除統合(Q3)を支える順序の持ち方。

### 案A: 密な連番(1..N)+ 並び替え時に区画全体を書き換え(推奨)

- Episode は所属区画(chapter_id、章なしは NULL)内で 1..N の連番。Chapter は Work 内で 1..N。
- 並び替え・移動・章削除統合の API は「**区画の新しい順序全体**」を受け取り、1トランザクションで該当区画の position を振り直す。
- 長所: モデルが自明で、どんな操作(swap・任意位置挿入・章跨ぎ移動・統合)も「最終形を書く」の1パターンに落ちる。中間状態の整合を考える必要がなく、**自己修復的**(次の全体書き換えで常に正規化される)。読み取りは `ORDER BY position` だけ。
- 短所: 並び替えのたびに区画内全行 UPDATE。ただし1作品のエピソード数は高々数百〜千のオーダーで、並び替えは Author の低頻度操作。問題にならない。
- UI との整合: カクヨムの並び替え画面は目次全体をドラッグで組み替えて保存する形であり、「全体順序を送る」API と自然に一致する。

### 案B: 隙間つき整数(1000, 2000, ...)+ 中間値挿入

- 単発の移動が1行 UPDATE で済むが、隙間枯渇時のリバランスという第2の実装パスが必要。高頻度・大規模リストの最適化であり、本件の負荷特性では複雑さに見合わない。

### 案C: 分数ランク / LexoRank(文字列順序キー)

- リバランス不要だがキーが人間に不可読になり、「目次の並び」というドメインの中心概念のデバッグ性を損なう。同上で過剰。

### 案D: 連結リスト(prev_id)

- 読み取り(ORDER BY 不可、再帰 CTE が必要)が最悪。不採用。

**結論(確定 2026-07-06・DQ1)**: 案Aを採用。この比較は ADR-BE の題材。

### position に一意制約を張らない(決定)

`UNIQUE(work_id, chapter_id, position)` は張らない。決め手は2つ:
1. **MySQL のユニーク索引は NULL を重複とみなさない**ため、章なし区画(chapter_id = NULL)では制約がそもそも効かない。効かせるには生成カラム等の迂回が必要で複雑さに見合わない。
2. MySQL には遅延制約(deferrable)がなく、振り直し UPDATE の途中経過が制約に衝突する(負値退避などの2段階更新が必要になる)。

一意性・連番性はトランザクション内の全体書き換え(案A)とテストで担保し、`INDEX(work_id, chapter_id, position)`(非ユニーク)を読み取り用に張る。

---

## 3. 派生値の持ち方

### 3.1 最終公開日時 → works.latest_published_at に非正規化(確定・DQ2)

一覧(U1/U2)のソートキー「配下 published Episode の published_at の最大値」の持ち方。

- **案X: 都度集計** — 一覧クエリのたびに `MAX(published_at)` をサブクエリ/JOIN で計算。ソートがインデックスに乗らず、公開作品数に比例して一覧(サイトの顔・最頻アクセス)が重くなる。
- **案Y: works.latest_published_at に非正規化(採用)** — 更新イベントは状態遷移表とちょうど一致する:
  - T2 publish / T4 時刻到来: `latest_published_at = GREATEST(COALESCE(latest_published_at, published_at), published_at)`
  - T7 unpublish: 残存 published の `MAX(published_at)` で再計算(0件なら NULL)
  - これらは**どのみち ISR 再検証を発火する箇所**なので、更新漏れの心配が同じ場所に集約される。
- 整合の検算はテスト+(必要なら)夜間の整合チェックコマンドで担保。ADR-BE 題材。

### 3.2 character_count → episodes に保存(確定済み)

本文保存時にアプリで算出(スコープリスト C「簡略化」の確定)。

### 3.3 応援数 → カウンタキャッシュを持たない(確定・DQ7)

目次・一覧に焼き込む応援数(Q7)は ISR 再生成時に `COUNT(*)`(`INDEX(episode_id)` で十分)。再生成は時間ベース revalidate の低頻度なので集計コストは無視できる。書き込みホットスポット(1行のカウンタ更新競合)をわざわざ作らない。応援が高頻度になった場合の counter cache 移行は ADR に「将来の最適化」として記録。

---

## 4. テーブル定義

型・NULL 可否・デフォルトの一覧。文字数上限(VARCHAR の長さ)は Q9 の方針(実物に寄せる/運用値を明記)で `validation-spec.md` にて確定し、ここでは仮置き値に「※Q9」を付す。

### users

| カラム | 型 | 制約 | 備考 |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 内部専用 |
| uuid | CHAR(36) | NOT NULL, UNIQUE | 公開 ID(UUIDv7・アプリ側生成) |
| name | VARCHAR(50) ※Q9 | NOT NULL | 表示名(作者名を兼ねる) |
| email | VARCHAR(255) | NOT NULL, UNIQUE | |
| password | VARCHAR(255) | NOT NULL | bcrypt/argon2 ハッシュ |
| created_at / updated_at | DATETIME | NOT NULL | |

メール確認(email_verified_at)は認証最小方針により**意図的省略**(ADR 記録)。

### genres(固定マスタ・シーダー投入)

| カラム | 型 | 制約 | 備考 |
|---|---|---|---|
| id | TINYINT UNSIGNED | PK | 固定マスタなので UUID 不使用 |
| slug | VARCHAR(30) | NOT NULL, UNIQUE | URL 用(`/genres/fantasy` 等) |
| name | VARCHAR(30) | NOT NULL | 表示名 |

timestamps なし(不変マスタ)。5〜6 件をシーダーで投入。

### works

| カラム | 型 | 制約 | 備考 |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 内部専用 |
| uuid | CHAR(36) | NOT NULL, UNIQUE | 公開 ID(UUIDv7) |
| user_id | BIGINT UNSIGNED | NOT NULL, FK→users | 所有者(Author) |
| genre_id | TINYINT UNSIGNED | NOT NULL, FK→genres | 単一選択 |
| title | VARCHAR(100) ※Q9 | NOT NULL | |
| catchphrase | VARCHAR(60) ※Q9 | NULL | 任意項目 |
| theme_color | VARCHAR(30) | NULL | プリセット54色のキーを保存(値は実装時受領・Q8)。NULL = 未設定(デフォルト色) |
| synopsis | TEXT | NULL | あらすじ(任意) |
| visibility | VARCHAR(10) | NOT NULL, DEFAULT 'private', CHECK | `public` / `private`。作成直後は private |
| serialization_status | VARCHAR(10) | NOT NULL, DEFAULT 'ongoing', CHECK | `ongoing` / `completed` |
| latest_published_at | DATETIME | NULL | **非正規化**(§3.1)。published エピソード0件なら NULL |
| created_at / updated_at | DATETIME | NOT NULL | |

### chapters

| カラム | 型 | 制約 | 備考 |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 内部専用 |
| uuid | CHAR(36) | NOT NULL, UNIQUE | 公開 ID(UUIDv7)。並び替え API で章を指定するために公開 |
| work_id | BIGINT UNSIGNED | NOT NULL, FK→works | |
| title | VARCHAR(100) ※Q9 | NOT NULL | |
| position | INT UNSIGNED | NOT NULL | Work 内 1..N(案A) |
| created_at / updated_at | DATETIME | NOT NULL | |

### episodes

| カラム | 型 | 制約 | 備考 |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 内部専用 |
| uuid | CHAR(36) | NOT NULL, UNIQUE | 公開 ID(UUIDv7) |
| work_id | BIGINT UNSIGNED | NOT NULL, FK→works | 章に属していても Work へ直接 FK を持つ(可視性・一覧クエリの JOIN 短縮) |
| chapter_id | BIGINT UNSIGNED | NULL, FK→chapters | NULL = 章なし区画(先頭) |
| title | VARCHAR(100) ※Q9 | NOT NULL | |
| body | MEDIUMTEXT | NOT NULL | TEXT(64KB)では日本語長編1話(数万字 × utf8mb4 最大4バイト)が溢れ得るため MEDIUMTEXT |
| status | VARCHAR(10) | NOT NULL, DEFAULT 'draft', CHECK | `draft` / `scheduled` / `published` |
| scheduled_at | DATETIME | NULL, CHECK | `(status='scheduled') = (scheduled_at IS NOT NULL)` — 不変条件4を DB でも表明 |
| published_at | DATETIME | NULL | **publish のたびに毎回更新**(DQ4 確定)。再公開すると一覧で再浮上する仕様と割り切る |
| position | INT UNSIGNED | NOT NULL | 所属区画内 1..N(案A) |
| character_count | INT UNSIGNED | NOT NULL, DEFAULT 0 | 保存時に算出する派生値 |
| created_at / updated_at | DATETIME | NOT NULL | |

### cheers

| カラム | 型 | 制約 | 備考 |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | サロゲート(Eloquent が複合 PK 非対応)。**公開 ID なし**(User × Episode で特定できる) |
| user_id | BIGINT UNSIGNED | NOT NULL, FK→users | |
| episode_id | BIGINT UNSIGNED | NOT NULL, FK→episodes | |
| created_at | DATETIME | NOT NULL | updated_at 不要(作成/削除のみのトグル) |

`UNIQUE(user_id, episode_id)` — 二重応援防止(不変条件5)。取り消し=行 DELETE、再応援=再 INSERT。

### favorites

| カラム | 型 | 制約 | 備考 |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | **公開 ID なし**(User × Work で特定できる) |
| user_id | BIGINT UNSIGNED | NOT NULL, FK→users | |
| work_id | BIGINT UNSIGNED | NOT NULL, FK→works | |
| created_at | DATETIME | NOT NULL | |

`UNIQUE(user_id, work_id)`。

### cheer_comments

| カラム | 型 | 制約 | 備考 |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 内部専用 |
| uuid | CHAR(36) | NOT NULL, UNIQUE | 公開 ID(UUIDv7)。削除 API がコメントを指定するために公開 |
| episode_id | BIGINT UNSIGNED | NOT NULL, FK→episodes | |
| user_id | BIGINT UNSIGNED | NOT NULL, FK→users | 書き手 |
| body | TEXT ※Q9 | NOT NULL | 編集機能はなし(投稿・一覧・削除のみ) |
| created_at / updated_at | DATETIME | NOT NULL | |

---

## 5. インデックス設計(クエリからの逆算)

| # | クエリ(ユースケース) | インデックス | 備考 |
|---|---|---|---|
| I1 | 作品一覧: `visibility='public' AND latest_published_at IS NOT NULL ORDER BY latest_published_at DESC`(U1) | works `(visibility, latest_published_at DESC)` | published 0件の作品は一覧に**載せない**(DQ5 確定)。目次ページ自体は URL 直撃で見える |
| I2 | ジャンル別一覧(U2) | works `(genre_id, visibility, latest_published_at DESC)` | |
| I3 | 目次・読書順: `work_id = ? ORDER BY chapter_id(区画), position`(U3〜U5, U26) | episodes `(work_id, chapter_id, position)` | 非ユニーク(§2)。prev/next はこの並びをアプリで合成して算出 |
| I4 | スケジューラ走査: `status='scheduled' AND scheduled_at <= now`(U29) | episodes `(status, scheduled_at)` | 毎分 tick の主キー。案Aの心臓部 |
| I5 | latest_published_at 再計算・公開話数/文字数集計: `work_id=? AND status='published'`(T7 時) | episodes `(work_id, status, published_at)` | I3 と別に持つ(I3 は status を含まない) |
| I6 | 応援数集計(ISR 再生成時、§3.3) | cheers `(episode_id)` | UNIQUE(user_id, episode_id) は探索(トグル判定)側で利用 |
| I7 | お気に入り一覧(U8) | favorites `(user_id, created_at DESC)` | UNIQUE(user_id, work_id) はトグル判定側 |
| I8 | コメント一覧(U11) | cheer_comments `(episode_id, created_at DESC)` | |
| I9 | 自分の作品一覧(Author 管理画面) | works `(user_id)` | |
| I10 | 公開 ID からの解決: `WHERE uuid = ?`(URL/API の全エントリポイント) | users / works / chapters / episodes / cheer_comments 各 `UNIQUE(uuid)` | ハイブリッド ID 方式(§1.1)の入口。定義は §4 に含まれる |

FK カラムには MySQL が自動で索引を要求するため、上記に含まれない FK 単独索引は Laravel の `foreignId()->constrained()` が張るものをそのまま使う。

---

## 6. 外部キーと削除時の動作

| FK | ON DELETE | 根拠 |
|---|---|---|
| works.user_id → users | RESTRICT | ユーザー削除はスコープ外(意図的省略) |
| works.genre_id → genres | RESTRICT | マスタは消さない |
| chapters.work_id → works | **CASCADE** | Work 削除のカスケード(不変条件8)は DB に任せる |
| episodes.work_id → works | **CASCADE** | 同上 |
| episodes.chapter_id → chapters | **SET NULL** | 章削除の「前章への統合」(Q3)は**アプリ層の操作**(エピソードを移動してから空章を DELETE)であり、この FK 動作には依存しない。SET NULL は Work カスケード削除時に chapters→episodes の削除順序で RESTRICT が衝突するのを避けるための安全弁 |
| cheers.episode_id → episodes | **CASCADE** | エピソード削除・Work カスケードで連鎖削除 |
| cheers.user_id → users | RESTRICT | |
| favorites.work_id → works | **CASCADE** | |
| favorites.user_id → users | RESTRICT | |
| cheer_comments.episode_id → episodes | **CASCADE** | |
| cheer_comments.user_id → users | RESTRICT | |

Work 削除のカスケードは DB(FK)に一任する。アプリ層で手動削除しない(削除前に ISR 失効対象を算出してから DELETE 1文、が最も単純)。

---

## 7. 判断の記録(DQ1〜DQ7・全回答済み 2026-07-06)

- **DQ1** → **案A(密な連番+区画全体書き換え)を承認**(§2)。
- **DQ2** → **works.latest_published_at に非正規化**(§3.1)。
- **DQ3** → **ハイブリッド方式**(BIGINT AUTO_INCREMENT 内部 PK + UUIDv7 公開 ID)を採用。単一 DB 前提では UUID 単一 PK の利点(グローバル一意)が使われず、索引・FK のサイズ不利だけが残るため。比較は §1.1。
- **DQ4** → **published_at は publish のたびに毎回更新する**(再公開で一覧に再浮上する仕様と割り切る)。
- **DQ5** → published 0件の public 作品は一覧に**載せない**(`latest_published_at IS NOT NULL` を一覧条件に含める。目次ページ自体は URL 直撃で見える)。
- **DQ6** → タイムゾーンは**全層 Asia/Tokyo に統一**(UTC 保存が実サービスの定石である旨は ADR に記録)。
- **DQ7** → 応援数のカウンタキャッシュカラムは**持たない**(ISR 再生成時に COUNT。§3.3)。

---

## 8. 次工程への引き継ぎ

- VARCHAR 長の確定値 → `validation-spec.md`(Q9)
- theme_color のプリセット54値 → 実装時受領(Q8)
- 案A・非正規化・ID 戦略(§1.1)の比較記録 → `ADR-BE`
- 遷移ガード・振り直しトランザクションの実装位置 → `implementation-rules.md`
- **内部 ID(auto increment)を API レスポンス・URL・ログに漏らさない規律**(§1.1 の代償)→ `implementation-rules.md` に強制ルールとして記載
