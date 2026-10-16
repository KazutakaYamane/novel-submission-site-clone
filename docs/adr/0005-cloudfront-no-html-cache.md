# 0005: HTMLをどこでキャッシュするか

- ステータス: 採用
- 日付: 2026-10-07

## 問題

CloudFrontはオリジンの`Cache-Control`に従うため、HTMLはNext.jsとCloudFrontの両方にキャッシュされる。一方、バックエンドからの`revalidateTag`が破棄するのはNext.jsのキャッシュだけで、CloudFrontのキャッシュは残る。

## 採用

HTMLを返す`default`のキャッシュ動作に`Managed-CachingDisabled`を設定し、CloudFrontではHTMLをキャッシュさせない。

## 却下した案

キャッシュの破棄が必要なときにCloudFrontの`CreateInvalidation`も呼ぶ案。CloudFrontのキャッシュはURL(ワイルドカード可)単位でしか破棄できない。1つのタグに関係するページはトップページやランキングなど別々のURLに散らばるため、バックエンドがタグごとに破棄対象のURL一覧を持つ必要がある。

## 代償

HTMLへのリクエストはすべて東京リージョンのNext.jsまで届く。
- 海外の読者へのレスポンスが遅くなる。
- アクセスが集中したとき、負荷をCloudFrontで吸収できず、Next.jsとElastiCacheが直接負荷を受ける。
