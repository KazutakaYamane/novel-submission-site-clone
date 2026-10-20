# 0011: Sanctum の API トークンで認証する

- ステータス: 採用(実装前)
- 日付: 2026-10-15
- 置き換えるもの: [0008](0008-backend-layers.md) の層の表の「認証セッションの操作」とログインの方法、[0004](0004-redis-shared-cache.md) の補足のうちセッションをRedisに置く記述

## 採用

Sanctum の API トークンで認証し、Laravel はセッションを使わない。Next.js がトークンを httpOnly cookie に持ち、Laravel へ `Authorization: Bearer` で送る。
