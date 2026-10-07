---
name: interviewer-docs
description: Structure and content rules for interviewer-facing docs (README.md, docs/adr/*.md). Use when writing or editing the README or an ADR.
---

Readers are interviewers seeing the project for the first time. Write in Japanese and follow the writing style in AGENTS.md.

## README

Order: what the app does → architecture diagram → tech stack with a one-line reason each, linking the ADR → local setup commands → implemented vs planned.

Include a section on AI usage: that Claude Code is used, and which parts the author decided and verified (design decisions, ADRs, tests).

## ADR

Sections: Context / Decision / Alternatives considered (why rejected) / Consequences (including accepted trade-offs).

Planned ADRs: ADR-TF, ADR-BE, ADR-FE.

Write only decisions that the current code and infrastructure embody. Leave out anything that comes from the working session: incidents during development, the order the author did things, a temporary state, or a user's passing remark. An alternative belongs under "Alternatives considered" only if a reader would plausibly ask "why not that?" about the finished system, and the reason must hold without knowing the session.

## Both

- Back claims with concrete facts: service names, numbers, config values, file paths.
- Mark planned work as planned; never describe it as done.

## Writing style (all prose: docs, READMEs, ADRs)

Rewrite any sentence that breaks a rule.
1. Start with the main statement. Do not open with an unneeded contrast, negation, or hedge.
   - NG: 「単にコンテナ化するだけではなく、〜」 / OK: 「LaravelとNext.jsを別のECSサービスとしてデプロイする。」
2. Name the concrete action and target. No abstract words or metaphors.
   - NG: 「スケーラブルな基盤を実現する」 / OK: 「ALBのターゲットグループを2つに分け、`/api/*`をLaravelに転送する。」
3. Do not dress up content with fragments, parallel phrasing, odd commas, or forced nominalization.
   - NG: 「コストは最小に、可用性は最大に。」 / OK: 「ISRのキャッシュをElastiCacheに置き、Next.jsの全タスクで共有する。」
4. Do not write text just to fill a heading, template section, or list. Delete it or write 「なし」.
5. Keep technical terms as Japanese engineers write them (identifier, API name, or katakana). Do not literally translate.
   - NG: 「再検証」(revalidate)、「起源」(origin) / OK: 「`revalidateTag`」「オリジン」

