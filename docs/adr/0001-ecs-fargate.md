# 0001: Next.jsとLaravelを別々のECS on Fargateサービスで動かす

- ステータス: 採用(一部を [0010](0010-browser-talks-only-to-nextjs.md) が置き換え)
- 日付: 2026-10-07

本プロジェクトはカクヨムを模した小説投稿サイトである。フロントエンドはNext.js、バックエンドはLaravel(JSON API)で構成し、AWS上のインフラをTerraformで管理する。

## 採用

Next.jsとLaravelはそれぞれ別のECS on Fargateサービスとして動かす。2022年の登壇資料によると、カクヨムはNext.jsのSSRサーバーをWebアプリ本体と同じくECS Fargateで動かしている([カクヨムの10年を見据えた技術 インフラ編 ── Hatena Engineer Seminar #19](https://speakerdeck.com/7474/kakuyomufalse10nian-wojian-ju-etaji-shu-nil-nil-inhurabian-nil-nil-awsakauntotoiaasfalseduan-jie-de-yi-xing-nil-nil-hatena-engineer-seminar-number-19))。本プロジェクトはこの構成に合わせる。

> 構成図の`/api/*`は superseded by [0010](0010-browser-talks-only-to-nextjs.md)

```
CloudFront ─┬─ default                    → ALB → Next.js
            ├─ /api/*                     → ALB → Laravel :80
            ├─ /_next/image*              → ALB → Next.js
            └─ /_next/static/*, /static/* → S3

Next.js ─(Service Connect)→ Laravel :8080

Next.js ─┬─→ ElastiCache (Redis, Next.js と Laravel で共用)
Laravel ─┘
Laravel ───→ RDS MySQL
```

## 却下した案

VercelやAWS Amplify HostingでNext.jsを動かす案は、SSR/ISRの運用は楽になるが、登壇資料のカクヨムの構成と異なるため採用しない。
