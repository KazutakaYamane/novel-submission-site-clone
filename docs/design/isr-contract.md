# 論理設計: ISR連携契約(たたき台)

入力: `state-machine.md` §5(ドメインイベント→再検証対象) / `api-contract.md` §3.1(データソース) / CLAUDE.md(ElastiCache backend の cacheHandler、ECS 2サービス、Service Connect)。
役割: 「Laravel のドメインイベントが、どのタグを、どの経路で失効させるか」の契約。フェーズ3実装の設計入力であり、構築済みインフラ(ISR + ElastiCache 共有キャッシュ + on-demand revalidation)を照らす中心文書。

ステータス: **確定**(2026-07-06 IQ1〜IQ5 全承認。記録は §7)
最終更新: 2026-07-06

---

## 1. ページ一覧とレンダリング方式

| ページ(Next.js ルート) | 方式 | キャッシュタグ | 時間ベース revalidate |
|---|---|---|---|
| `/` および `/works/page/{n}`(作品一覧・更新順) | **ISR** | `works-list` | 300s(IQ2) |
| `/genres/{slug}/page/{n}`(ジャンル別一覧) | **ISR** | `works-list` | 300s |
| `/works/{workUuid}`(作品詳細=目次) | **ISR** | `work:{workUuid}` | 300s |
| `/works/{workUuid}/episodes/{episodeUuid}`(本文) | **ISR** | `episode:{episodeUuid}`, `work:{workUuid}` | 86400s(保険) |
| `/login` / `/register` | CSR | — | — |
| `/favorites`(お気に入り一覧) | SSR(個人化・キャッシュ不可) | — | — |
| `/my/*`(作者管理画面一式) | SSR/CSR(個人化) | — | — |
| コメント欄・応援ボタン・応援数(本文ページ内) | **CSR**(ISR HTML に含めない) | — | — |

- **一覧のページングはパスセグメント**(`/works/page/2`)にする(IQ3)。App Router の searchParams は動的レンダリング扱いになり ISR に乗らないため、クエリ文字列(`?page=2`)は使わない。
- **ビルド時のページ事前生成はしない**(`generateStaticParams` で DB を引かない)。CI のビルド環境から API/DB へ到達できる前提を作らないためで、全 ISR ページは初回アクセス時に生成(`dynamicParams: true`)。
- 一覧・目次に焼き込む応援数・話数などの集計値は、この時間ベース revalidate(300s)で遅れて反映される(Q7 の確定を実装に落としたもの)。

## 2. タグ設計

タグは3種類のみ。**エピソードページには `work:{uuid}` も併記する**のが要点(§3)。

| タグ | 付与対象 | 失効の意味 |
|---|---|---|
| `works-list` | 一覧系ページ全部(トップ・ジャンル別・全ページ番号) | 一覧の並び・件数・掲載内容が変わった |
| `work:{workUuid}` | 当該作品の目次ページ **+ 配下の全エピソードページ** | 作品単位で何かが変わった(メタ・構成・可視性・話の増減) |
| `episode:{episodeUuid}` | 当該エピソードページ | その話自体が変わった(改稿) |

### タグ粒度の判断(IQ1)

- **採用案: 作品単位の一括失効。** publish / unpublish / 並び替えは prev/next リンクを通じて「読書順で隣のエピソードページ」にも波及する(state-machine §5)。隣接ページを都度特定して精密に失効させる代わりに、配下全エピソードに `work:{uuid}` を付けて**作品ごと一括失効**する。
  - 根拠: ISR の失効は**遅延再生成**(次のアクセスまで再生成コストが発生しない)なので、過剰失効のコストは「失効後に読まれたページの再生成1回」だけ。一方、隣接特定ロジック(削除・非公開を挟んだ読書順の隣、章跨ぎ、統合)はバグったとき**古い prev/next が残り続ける**という発見しにくい事故になる。正しさを単純さで買う。
  - 却下案: イベントごとに影響エピソードを列挙する精密失効。1作品のページ数程度では節約効果が再生成1〜2回分しかなく、複雑さに見合わない。
- ジャンル別一覧に `genre:{slug}` の個別タグは切らない(一覧はすべて `works-list` で一括)。一覧ページの再生成は軽く、粒度を細かくする利益がない。

## 3. 失効トリガー対応表(確定版)

state-machine §5 をタグに落とした実装契約。**Laravel 側はこの表のとおりに revalidate を発火する。**

| ドメインイベント(遷移) | 失効タグ |
|---|---|
| T2 publish / T4 予約時刻到来 | `episode:{uuid}` + `work:{workUuid}` + `works-list` |
| T7 unpublish | 同上 |
| T9/T10 delete(draft/scheduled のみなので公開面に影響なし) | **なし**(管理画面は SSR) |
| T8 revise(改稿) | `episode:{uuid}` + `work:{workUuid}`(目次の話タイトル・文字数、隣接ページの prev/next タイトル) |
| 目次全体更新(PUT structure)・章の作成/改名/削除 | `work:{workUuid}` |
| Work メタ変更(PATCH: title / catchphrase / theme_color / synopsis / genre) | `work:{workUuid}` + `works-list` |
| visibility 切替(PUT visibility) | `work:{workUuid}` + `works-list` |
| complete / reopen | `work:{workUuid}` + `works-list` |
| Work 削除 | `work:{workUuid}` + `works-list`(該当ページは再生成時に 404 化) |
| 応援(PUT/DELETE cheer)・お気に入り・コメント投稿/削除 | **なし**(on-demand 失効しない。数字は 300s の時間ベースで追随、コメント欄は CSR) |

補足: draft 状態の Episode に対する編集・作成はどの公開ページにも影響しないため発火なし。T9/T10 も同様(scheduled は公開面に未出現)。

## 4. 再検証の呼び出し経路

```
Laravel (ドメインイベント)
  → DB コミット後にキュー投入(Redis, afterCommit)
  → ジョブが Next.js の再検証エンドポイントを HTTP で叩く
      POST http://web:3000/api/revalidate   ← East-West(ローカル: compose のサービス名 / 本番: ECS Service Connect)
      Header: x-revalidate-secret: <共有シークレット>
      Body: {"tags": ["episode:...", "work:...", "works-list"]}
  → Next.js 側 route handler が revalidateTag() を実行
  → cacheHandler(ElastiCache)上の共有キャッシュが失効 → 全タスクに即時反映
```

- **共有シークレット**: 環境変数で両サービスに注入(ローカル: .env / 本番: Secrets Manager)。不一致は 401。
- **ElastiCache 共有が前提**: キャッシュ本体とタグ情報が全 Next.js タスクで共有されているため、**どれか1タスクに1回届けば全タスクに効く**(タスクごとの個別呼び出しは不要)。これが ISR の cacheHandler を ElastiCache に置いた理由の実演になる。
- **信頼性の方針(IQ5)**:
  - 発火は**キュー経由**(同期 HTTP にしない)。公開 API のレスポンスタイムに Next.js の応答時間を混ぜない。
  - **afterCommit** で投入(コミット前に失効させると、再生成が旧データを読んで古いページを焼き直す競合が起きる)。
  - ジョブは指数バックオフでリトライ(例: 3回)。**最終失敗しても公開処理は成功のまま**(状態遷移と再検証の失敗を分離)。取りこぼしの安全網は時間ベース revalidate(§1)と、次のイベントによる上書き。
  - ジョブは冪等(同じタグを2回失効させても無害)なので、at-least-once 配送で問題ない。

## 5. CloudFront との関係(IQ4)

on-demand revalidation の即時性は「**CloudFront に HTML キャッシュを置かない**」ことで成立させる。

| ビヘイビア | キャッシュ方針 |
|---|---|
| `/_next/static/*` | **長期キャッシュ**(immutable。ファイル名にハッシュ入りのため無限 TTL 可) |
| `/api/*`(→ Laravel) | キャッシュ無効 |
| default(→ Next.js、HTML・RSC ペイロード) | **キャッシュ無効(CachingDisabled 相当)**。ISR の HTML キャッシュは Next.js 層(ElastiCache)にのみ存在させる |

- 理由: CloudFront に HTML を持たせると revalidateTag が効かない層が挟まり、失効のたびに CreateInvalidation(遅い・従量課金・パス指定でタグ粒度が表現不能)が要る。キャッシュの階層を1つにするのが on-demand ISR の設計上の要点。
- Next.js は ISR ページに `Cache-Control: s-maxage` を自動付与するが、CloudFront 側のキャッシュポリシーで無視する(オリジンヘッダに依存させない)。
- 個人化 SSR(`/my/*`, `/favorites`)が誤ってエッジにキャッシュされる事故(CLAUDE.md の警告)も、この「default 非キャッシュ」で構造的に防ぐ。

## 6. ローカル開発での再現

| 項目 | 本番 | ローカル(compose) |
|---|---|---|
| Laravel → Next.js 再検証 | Service Connect `http://web:3000` | サービス名 `http://web:3000`(frontend プロファイル起動時) |
| cacheHandler の Redis | ElastiCache | `redis:6379`(Laravel と同居、DB 番号を分ける) |
| web 停止中の再検証 | — | ジョブは接続失敗→リトライ→破棄(公開処理には影響しない設計の確認になる) |

## 7. 判断の記録(IQ1〜IQ5・全承認 2026-07-06)

- **IQ1** → 承認: タグ粒度 = **作品単位の一括失効**(エピソードページに `work:{uuid}` を併記し、隣接ページの精密特定をしない)(§2)。
- **IQ2** → 承認: 時間ベース revalidate の値 = **一覧・目次 300s / 本文 86400s(保険)**。応援数の反映遅延は最大約5分という UX 上の割り切り。
- **IQ3** → 承認: 一覧のページングは**パスセグメント**(`/works/page/2`。App Router の ISR 制約のため)。
- **IQ4** → 承認: CloudFront は **HTML を一切キャッシュしない**(`/_next/static/*` のみ長期キャッシュ)(§5)。
- **IQ5** → 承認: 再検証発火は**キュー経由・afterCommit・リトライ3回・最終失敗は握って公開成功を維持**(§4)。

## 8. 次工程への引き継ぎ

- 発火箇所の実装位置(ドメインイベント→ジョブ投入の一元化、二重発火防止)→ `implementation-rules.md`
- 再検証シークレットの管理(Secrets Manager 追加)→ Terraform(実装フェーズ)
- CloudFront ビヘイビアのキャッシュポリシー確認(default = CachingDisabled になっているか)→ 実装フェーズで `terraform/modules/cloudfront` を照合
