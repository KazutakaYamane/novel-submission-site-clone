# 論理設計: 実装ルール(実装時に守るべき規約)

役割: 概念設計・論理設計で確定した判断を、実装がなし崩しに壊さないための強制ルール集。コードレビュー(セルフレビュー含む)のチェックリストとして使う。
入力: `domain-model.md`(不変条件1〜8) / `state-machine.md`(T1〜T10) / `db-schema.md` / `api-contract.md` / `isr-contract.md`。

ステータス: **たたき台**(質問形式の未確定点はなし。全体レビューで確定)
最終更新: 2026-07-06

---

## 1. レイヤリング(責務の固定)

```
Controller → UseCase → Domain(Model のドメインメソッド) → Eloquent(永続化)
```

| 層 | やること | やってはいけないこと |
|---|---|---|
| Controller | HTTP ⇔ アプリの変換のみ: FormRequest 受け取り、UseCase 呼び出し、API Resource で整形、例外→ステータスコード写像 | ビジネスロジック、クエリ組み立て、トランザクション |
| FormRequest | **形式**バリデーション(`validation-spec.md` の写し)と認可の前段(認証必須か)のみ | **ドメインルールを書かない**。「completed だから作成不可」「published だから削除不可」等のガードは FormRequest に置かない(422 と 409 の区別が消えるため) |
| UseCase | 1操作 = 1クラス(アクション型 POST と 1:1)。トランザクション境界。ドメインメソッド呼び出しの編成、ドメインイベント発行 | HTTP の関心事(Request/Response 型への依存) |
| Domain(Model) | 不変条件・遷移ガードの**唯一の実装場所**。`$episode->publish()` / `$work->complete()` などユビキタス言語のメソッド | コントローラやジョブから直接 `$episode->status = 'published'` のような属性代入をさせない |

- ディレクトリは `app/UseCases/`(または `app/Actions/`)に動詞で切る(`PublishEpisode`, `UpdateWorkStructure`, ...)。
- 「フレームワーク非依存の設計」の実演ポイント: 不変条件と遷移は Laravel の機能(FormRequest / Observer / ミドルウェア)ではなく**ドメインメソッドの中**にある、という一点を崩さない。

## 2. 状態遷移の実装ルール

1. status を変更できるのは遷移メソッド(`publish` / `schedule` / `unschedule` / `unpublish` / スケジューラの `publishScheduled`)のみ。
2. `status` / `published_at` / `scheduled_at` / `position` / `character_count` / `latest_published_at` は **`$fillable` に含めない**(mass assignment での状態改変を構造的に禁止)。
3. 不正遷移・ガード違反はドメイン例外(`DomainRuleViolation` 系、機械可読 code 付き)を投げ、ハンドラで一律 409 に写像する(`api-contract.md` §5 の code 一覧と1:1)。
4. 遷移表 T1〜T10 の各行・各ガードに**対応するテストを必ず書く**(§8)。
5. published_at は publish のたびに上書き(DQ4)。scheduled_at は schedule/unschedule 以外で触らない(CHECK 制約が守ってくれるが、例外を DB エラーで知るのではなくドメイン側で先に検証する)。

## 3. トランザクションと並行制御

| 操作 | ルール |
|---|---|
| すべての書き込み UseCase | `DB::transaction()` で全体を包む(1 UseCase = 1 トランザクション) |
| 目次全体更新(structure)・章削除(前章統合)・エピソード作成(末尾 position 採番) | **Work 行を `lockForUpdate()`** してから区画を書き換える(並び替え同士・採番同士の競合を Work 単位で直列化)。position の整合は「全体書き換え」(db-schema §2)がロック下で行われることで保証される |
| 応援・お気に入りのトグル | UNIQUE 制約を前提に、INSERT の重複キー例外は「既に応援済み」として握って 204(競合連打をアプリ検査で防ごうとしない)。DELETE は消せなくても 204(冪等) |
| 予約公開ジョブ | スケジューラは `withoutOverlapping()`。対象行は `lockForUpdate()` で取得し、遷移は1件ずつ独立トランザクション(1件の失敗が同 tick の他件を巻き込まない)。取り損ねは次 tick で自己修復 |
| latest_published_at の更新 | publish / unpublish / 予約公開ジョブの**同一トランザクション内**で更新(db-schema §3.1 の式)。トランザクション外での後追い更新をしない |

## 4. ISR 再検証発火の一元化

1. 発火が必要なドメインイベント(`isr-contract.md` §3 の表)は、UseCase が **Laravel Event を発行** → Listener がキュージョブを投入、の一経路のみ。UseCase やモデルから再検証 HTTP を直接叩かない。
2. ジョブ投入は **`afterCommit`**(コミット前失効の競合防止・IQ5)。
3. §3 の表にないイベントで発火しない(draft の編集・T9/T10 削除・応援・コメントは発火なし)。「念のため失効」を書かない — 表が正。
4. 再検証ジョブはリトライ3回(指数バックオフ)、最終失敗はログのみ(公開処理の成否と分離)。
5. Next.js 側 route handler は共有シークレット検証 → `revalidateTag()` のみ。ロジックを持たせない。

## 5. ID・シリアライズの規律(ハイブリッド ID の代償)

1. **内部 ID(auto increment)を境界の外に出さない**: API Resource は `uuid` のみを `id` 相当として出力し、内部 `id` はいかなるレスポンス・URL・ログメッセージにも含めない。
2. API Resource を介さない `toArray()` / `toJson()` のレスポンス直返しを禁止(Resource 経由を必須にすることで 1 を構造的に守る)。
3. ルートモデルバインディングは uuid カラムで解決(`getRouteKeyName()` = 'uuid')。
4. JOIN・FK・内部処理は内部 ID を使う(uuid で JOIN しない)。

## 6. 可視性・認可の実装ルール

1. 公開系の読み取りクエリは必ず**共通スコープ経由**: `Work::visible()`(visibility = public)、`Episode::visible()`(published かつ親 Work が public — 不変条件1の合成)。コントローラやユースケースで where を手書きしない。**「絞り込み条件の書き漏らし=未公開漏洩」というクラスの事故をスコープの一元化で潰す**(案Aの決め手と同じ思想)。
2. 管理系の認可は Policy に集約(`WorkPolicy@manage`: 所有者のみ)。Chapter / Episode / 構成操作はすべて親 Work の Policy を通す。
3. 非可視リソースの 404 秘匿(AQ5)は、visible スコープで引けない=`ModelNotFoundException` に自然に落とす(明示の分岐を書かない)。
4. コメント削除だけは二重権限(書き手本人 or エピソードの Author — 不変条件7)。CheerCommentPolicy に明記。

## 7. 命名・ユビキタス言語

1. `domain-model.md` §1 の英語名を、クラス・メソッド・カラム・API パス・テスト名まで**唯一の語彙**とする。
2. 禁止語彙(揺れの芽): Novel / Story / Post / Article(→ Work)、Section / Part / Volume(→ Chapter)、Like / Kudos(→ Cheer)、Bookmark / Follow(→ Favorite)、状態値の言い換え(open/closed, active/inactive 等)。
3. 遷移メソッド名は操作用語表のとおり(publish / schedule / unschedule / unpublish / revise / complete / reopen)。`setStatus` / `updateStatus` のような無意味な動詞を作らない。

## 8. テスト方針(設計文書 → テストの写像)

**設計の表がそのままテストの仕様である**。以下の写像を必須とする:

| 設計文書 | テスト |
|---|---|
| 遷移表 T1〜T10 + ガード(state-machine §1) | ユニット: 各遷移の成功と、各ガード違反が正しい code の例外になること(全行網羅) |
| 不変条件1〜8(domain-model §3) | ユニット/Feature: 各不変条件を破る操作が拒否されること |
| 可視性マトリクス(state-machine §4) | Feature: visibility × status × アクターの全組み合わせで見える/404 |
| 発火表(isr-contract §3) | Feature: 各イベントで**期待どおりのタグのジョブが投入される**こと(Queue::fake)。発火なし行は「投入されない」ことも検証 |
| API 契約(api-contract §3・§5) | Feature: 各エンドポイントの正常系ステータス+401/403/404/409/422 の代表ケース |
| validation-spec §2 | Feature: 境界値(上限ちょうど / +1) |

- ツール: Pest(構築済み)。静的解析 PHPStan は最大レベルを目標に、逸脱は baseline でなく修正で解消。Pint は CI で `--test`。
- 予約公開は `Carbon::setTestNow` で時刻を固定してテスト(実時間 sleep 禁止)。

## 9. マイグレーション・シーダー規約

1. 1テーブル = 1マイグレーション。CHECK 制約(db-schema §1)は `DB::statement` で同マイグレーション内に書く(スキーマの正は migration ファイル)。
2. インデックスは I1〜I10(db-schema §5)の番号をマイグレーションのコメントに残す(なぜこの index があるかを設計文書に遡れるように)。
3. genres はシーダーで投入(5〜6件)し、テストでも同一シーダーを使う。マスタの値をテスト内にハードコードしない。
4. 本番適用は CD パイプラインの migrate ステップのみ(手動 migrate 禁止)。

## 10. その他

- 時刻取得は `now()`(app TZ = Asia/Tokyo・DQ6)。`new DateTime` や `date()` を混ぜない。
- 設定値(per_page、revalidate 秒数、リトライ回数、上限値)は config に集約し、マジックナンバーをコードに散らさない。
- この文書と設計文書群が矛盾したときは、**設計文書を直してから実装する**(実装側で黙って逸脱しない)。
