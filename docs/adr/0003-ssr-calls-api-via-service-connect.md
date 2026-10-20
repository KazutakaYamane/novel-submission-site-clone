# 0003: SSRからAPIをどう呼ぶか

- ステータス: superseded by [0010](0010-browser-talks-only-to-nextjs.md)
- 日付: 2026-10-07

## 問題

SSRからもブラウザと同じくCloudFront経由でバックエンドを呼ぶと、バックエンドに届く送信元IPがすべてNext.js ECSタスクのIPになる。このためIP単位のレート制限が機能しない。

## 採用

バックエンドにService Connect専用のポート8080を設ける。SSRは`http://api:8080`を呼び、ユーザーのIPを渡す。ブラウザはCloudFront → ALB経由で、ポート80の`/api/*`を呼ぶ。

## 代償

APIクライアントは、サーバー側で`http://api:8080`、ブラウザ側で`/api`とbase URLを切り替える必要がある。
