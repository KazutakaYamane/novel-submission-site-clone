# 0009: Next.js のキャッシュを Cache Components で構成する

- ステータス: 採用(実装前)
- 日付: 2026-10-15
- 置き換えるもの: [0002](0002-ssr-isr-per-page.md)、[0004](0004-redis-shared-cache.md) のうちNext.jsのキャッシュの置き場所

## 問題

(作者が書く)

## 採用

Next.js は Cache Components(`cacheComponents: true`)で動かす。公開ページは、`"use cache"` でキャッシュする部分と、`<Suspense>` の中でリクエストごとに描画する部分に分ける。キャッシュの2層(`cacheHandler`・`cacheHandlers`)はどちらも Redis に置く。

## 却下した案

(作者が書く)

## 代償

(作者が書く)
