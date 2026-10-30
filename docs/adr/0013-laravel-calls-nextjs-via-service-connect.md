# 0013: Laravel は Service Connect 経由の通信で Next.js のキャッシュを破棄する

- ステータス: 採用(実装前)
- 日付: 2026-10-31
- 置き換えるもの: [0007](0007-prod-cost-compromises.md) のSGのうちweb(Next.js)の受信元

## 採用

Laravel(app・worker)はService Connectの`web:3000`経由でNext.jsの`/api/revalidate`を呼ぶ。

「Service Connectは web → api の一方向、web の受信は ALB からだけ」という決定にapi → webの経路を追加。
