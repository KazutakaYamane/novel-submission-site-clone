# frontend/AGENTS.md

Rules for writing code under `frontend/`. Read the root `AGENTS.md` first.

Next.js 16.3 (App Router, `cacheComponents: true`), React 19.2, TypeScript. This Next.js differs from older versions in training data: before using a Next.js API, read its page in `node_modules/next/dist/docs/` and follow it over memory.

Tags on each rule:
- MUST / SHOULD / MAY: how strictly to follow it.
- [spec]: Next.js or React behavior, documented in `node_modules/next/dist/docs/`. [decision]: a choice made for this project.

## Commands

Node runs in the container: `docker compose exec web <cmd>`

- Lint: `npm run lint`
- Type check: `npm run typecheck` (`next typegen` generates `PageProps` / `LayoutProps`, then `tsc --noEmit`)
- Unit test: `npm run test [-- path]` (Vitest)
- Build: `npm run build`. Run it after changing any route: missing `<Suspense>` boundaries and `server-only` violations fail only at build.
- E2E: `docker compose run --rm e2e` (Playwright, `e2e/tests/`)

## Role

- MUST [decision] Render UI and relay requests to Laravel. Do not re-implement business validation or authorization; Laravel returns 422/403/404 and the UI shows them.
- MUST [decision] Only the Next.js server calls Laravel, at `INTERNAL_API_URL` (Service Connect). The browser talks only to Next.js; Laravel is not reachable from the internet.

## Directory layout

```
app/                         routes; files used by one route live in that route's folder
  <route>/page.tsx           composes boundaries; little logic
  <route>/<name>.tsx         components used only by this route (Server or Client)
  <route>/actions.ts         Server Actions used only by this route
  api/revalidate/route.ts    the only Route Handler: cache invalidation from Laravel
components/                  components used by 2+ routes, one file per component (+ its actions.ts if any)
lib/api/                     server-only: every call to Laravel
  client.ts                  fetch wrapper (base URL, token, X-User-IP, errors)
  schema.d.ts                generated from Laravel's OpenAPI (do not edit)
  <resource>.ts              data functions per API resource (works.ts, episodes.ts, me.ts …)
lib/auth.ts                  server-only: token cookie read/write, unauthenticated redirect (never calls Laravel)
lib/<topic>.ts               framework-free logic shared by 2+ routes (e.g. notation.ts)
```

- MUST [decision] Put a file in the route folder that uses it. Move it to `components/` or `lib/` only when a second route imports it. Reason: the route folder shows everything the page depends on.
- MUST [decision] Do not create role folders (`hooks/`, `utils/`, `types/`, `services/`, `containers/`). A hook or type lives next to its only user, or in the shared file that owns the concept.
- MUST [decision] Every module that calls Laravel or reads the token lives in `lib/api/` or `lib/auth.ts` and starts with `import "server-only";`. Reason: a Client Component import then fails the build [spec].
- MUST [decision] No import cycles, and `lib/auth.ts` does not import `lib/api/`. Reason: `lib/api/client.ts` imports `lib/auth.ts` for the token. Enforced by `npm run lint` (`import/no-cycle`, `import/no-restricted-paths`).
- MUST [decision] Unit tests sit next to the source as `<name>.test.ts(x)`.

## Boundaries

### Component-tree boundaries

| Boundary | Rule |
|---|---|
| `layout.tsx` | MUST [spec] not `await` `params`, `cookies()`, or `headers()` at the top level. Reason: it blocks every child segment and the static shell. Pass the promise down into a component inside `<Suspense>`. |
| `page.tsx` | SHOULD [decision] be a non-async component that only arranges cached sections and `<Suspense>` boundaries, so the boundaries are visible in one file. Pass `props.params` (a promise) down; await it inside the boundary [spec]. |
| `<Suspense>` | MUST [spec] wrap every component that reads `cookies()`, `headers()`, `params`, `searchParams`, `connection()`, or uncached data. Otherwise `next build` fails. MUST [decision] put one boundary around each independent per-request section (header account, cheer, rating) so a slow section does not hold the others. |
| `"use cache"` | MUST [decision] be placed on data functions in `lib/api/<resource>.ts`, not on components. Reason: the cache key is the function's arguments, which are IDs [spec: arguments are part of the key]. |
| `"use client"` | MUST [decision] be placed on leaf components that need state, effects, or browser events. Never on `page.tsx` or `layout.tsx`. |
| `error.tsx` / `not-found.tsx` | MUST [decision] have `app/error.tsx`, `app/global-error.tsx`, `app/not-found.tsx`. `error.tsx` is a Client Component [spec]. |
| `catchError` (`next/error`) | MUST [decision] wrap each per-request section on public pages (cheer, rating, header account) so a Laravel failure there does not replace the cached content with `error.tsx`. `catchError` lets `redirect()`/`notFound()` through [spec]. Shared wrapper: `components/section-error-boundary.tsx`. |

What goes where on public pages [decision]:

| Page | Cached (`cacheTag`) | Per request, inside `<Suspense>` |
|---|---|---|
| `/` | new works, top ranking, genres (`top`, `ranking`) | header account |
| `/rankings/[genre]/[period]` | ranking table (`ranking`) | header account |
| `/works/[workId]` | work, table of contents (`work:{id}`) | header account, my rating |
| `/works/[workId]/episodes/[episodeId]` | body, prev/next (`episode:{id}`, `work:{id}`) | header account, cheer count and my cheer |
| `/login`, `/signup`, `/session-expired`, `/my/*` | none | whole page |

- MUST [decision] Do not cache cheer counts. Reason: caching them would invalidate the episode cache on every cheer.
- MUST [decision] Do not use `generateStaticParams`. Reason: Laravel is unreachable during `next build`, and with Cache Components it must return at least one param [spec]. Unknown params are served from the App Shell and upgraded in the background [spec].
- MUST [spec] Routes without dynamic params (e.g. `/`) prerender at build. In such a route, a section that calls Laravel calls `await connection()` first, inside its `<Suspense>`, so the call runs at request time. The cached data function still caches the result.

### Module boundaries

- MUST [decision] `lib/api/client.ts` is the only place that calls `fetch`; other `lib/api/` files call `apiFetch` / `apiFetchAsUser`, and components call `lib/api/` functions. Enforced by `npm run lint` (`no-restricted-globals`).
- MUST [decision] Data functions return the types the UI needs, built from `schema.d.ts`. Client Components receive only these narrow types or primitives as props, never a raw API response. Reason: anything passed to a Client Component is sent to the browser [spec].
- MUST [decision] Server Actions live in `actions.ts` files with `"use server";` at the top. Do not define inline actions inside components.

## State

Decide in this order and stop at the first match:

1. Comes from Laravel → server data. Fetch in a Server Component and pass down as props. MUST NOT [decision] copy it into client state, except as the initial value of a form or an optimistic update.
2. Should survive reload or be shareable (search query, ranking genre/period, page number) → URL. Read `searchParams` in a Server Component inside `<Suspense>`; change it with `<Link>` or `router.push`.
3. Input being edited → form. Use uncontrolled inputs inside `Form` from `components/form.tsx`; MAY use `useState` when the UI reacts to each keystroke (the notation preview in the editor).
   - MUST [decision] Do not write `<form>` directly. Reason: React resets uncontrolled inputs after every `<form action>` submission, even when the action returns a 422 [spec], so the author's input would be lost. `Form` cancels the submit in `onSubmit` and calls the action inside `startTransition`, which does not reset. Enforced by `npm run lint` (`no-restricted-syntax`).
   - `Form` disables its fields until hydration. Reason: without `onSubmit`, a submission is a plain GET that puts the input in the URL.
4. Visible only in one component tree (open/closed, drag state in reorder) → `useState` / `useReducer` in the nearest common Client Component.
5. Needed by distant Client Components → React context with a provider placed as low as possible. MUST NOT [decision] add a global store library (Redux, Zustand, Jotai). Reason: items 1–4 cover every current screen.

## Data fetching

- MUST [decision] `lib/api/client.ts` exports two functions:
  - `apiFetch(path, init)`: no token, no `X-User-IP`. The only one allowed inside `"use cache"`, because `cookies()`/`headers()` throw there [spec].
  - `apiFetchAsUser(path, init)`: adds `Authorization: Bearer <token>` when the cookie exists, and `X-User-IP`. Never inside `"use cache"`.
- MUST [decision] `X-User-IP` is `cloudfront-viewer-address` without the port, falling back to the first `x-forwarded-for` entry. Reason: Laravel's IP rate limits read this header on port 8080.
- MUST [spec] Never pass the token or other secrets as an argument to a `"use cache"` function or into `cacheTag`. Reason: keys and tags are stored as plain text.
- MUST [decision] Cached data functions call `cacheTag` with every tag that should invalidate them (`top`, `ranking`, `work:{id}`, `episode:{id}`) and `cacheLife("days")`. Reason: invalidation comes from Laravel; `days` bounds staleness if an invalidation job fails.
- MUST [decision] A cached function returns `null` for a Laravel 404. The calling component calls `notFound()`.
- MUST [decision] Errors from `apiFetchAsUser`:
  - 401 → `redirectUnauthenticated()` from `lib/auth.ts`: redirect to `/session-expired` if the token cookie exists, else `/login`. Exception: `getCurrentUser()` in `lib/api/me.ts` returns `null` on 401 and does not redirect, for the header and other optional sections.
  - 403/404 → `notFound()` in pages; returned as an error result in Server Actions.
  - 422 → returned to the caller (only from Server Actions).
  - Other → throw; the nearest `catchError` or `error.tsx` handles it.
- MUST [spec] Server Components cannot set or delete cookies. A stale token cookie stays until the next login, demo start, or logout replaces or deletes it.

## Mutations

- MUST [decision] Every write goes through a Server Action that calls `apiFetchAsUser`.
- MUST [spec] Treat each Server Action as a public POST endpoint: anyone can call it with any arguments, including bound arguments.
- MUST [decision] Validate argument shapes in the action before calling Laravel (UUID format, string type and maximum length, enum membership) with small functions in the same file. Do not add a validation library without asking the user. Laravel does all business validation and authorization.
- MUST [decision] Form actions have the signature `(prev: ActionState, formData: FormData) => Promise<ActionState>` and are used with `useActionState`. `ActionState`, defined in `lib/action-state.ts` (importable from Client Components), is `{ status: "idle" } | { status: "ok" } | { status: "error"; message?: string; fieldErrors?: Record<string, string[]> }`. A 422 maps Laravel's `errors` object to `fieldErrors`.
- MUST [decision] On 401 inside an action: delete the token cookie, then redirect by the same rule as `redirectUnauthenticated()`.
- MUST [decision] After a successful write that changes what the current page shows, call `refresh()` from `next/cache` [spec: callable only inside Server Actions]. Do not call `revalidateTag` or `updateTag` from actions. Reason: public caches are invalidated by Laravel through `/api/revalidate`.
- MUST [decision] Fire-and-forget writes after render (page view recording) are a Client Component that calls a Server Action in `useEffect` [spec: documented pattern]. Do not record them during Server Component render. Reason: prefetches also render.

## Auth

- MUST [decision] Token cookie: name `auth_token`, `httpOnly`, `secure` (except in development), `sameSite: "lax"`, `path: "/"`, `maxAge` equal to Laravel's token expiration. Set only in the login, signup, and demo-start actions; delete only in the logout action and on 401 in actions.
- MUST [decision] Login checks in pages exist only to redirect guests. Laravel decides access on every request.
- MUST [decision] Do not authenticate in `proxy.ts`. Reason: validating the token needs a Laravel call, which would run on every request.

## Cache invalidation endpoint

- MUST [decision] `app/api/revalidate/route.ts` accepts `POST` with `Authorization: Bearer <REVALIDATE_SECRET>`, compared with `timingSafeEqual`; returns 401 when the secret is missing or wrong, even if `REVALIDATE_SECRET` is unset.
- MUST [decision] It accepts only `top`, `ranking`, `work:{uuid}`, `episode:{uuid}` (400 otherwise) and calls `revalidateTag(tag, { expire: 0 })` so a publish appears on the next request.

## Forbidden patterns

| Don't | Reason | Do instead |
|---|---|---|
| `"use client"` on `page.tsx`, `layout.tsx`, or a component near the top of a page | Everything it imports goes to the browser, and it cannot read server data | Keep the page a Server Component; mark only the interactive leaf |
| `fetch` (or a Server Action that reads data) inside `useEffect` | Waterfall after hydration, a loading state for data the server already had | Fetch in a Server Component and pass props |
| Put server data into a global store or context and update it there | Two sources of truth; the store goes stale after `refresh()` | Pass props; call `refresh()` after writes |
| `"use cache"` or `"use cache: private"` on per-user data | Shared cache could serve one user's data to another; private adds a cache layer this project does not use | Per-request read inside `<Suspense>` |
| Pass a raw API response object to a Client Component | Every field is sent to the browser | Map to a narrow type in `lib/api/` |
| Call Laravel from the browser, or add a Route Handler for reads | Laravel is not public; a read handler duplicates Server Components | Server Component for reads, Server Action for writes |
| `export const revalidate`, `dynamic`, `fetchCache` | Replaced by Cache Components [spec] | `"use cache"`, `cacheLife`, `<Suspense>` |
| `revalidateTag(tag)` with one argument, or `revalidateTag(tag, "max")` to show a publish | One-argument form is deprecated [spec]; `"max"` serves the old version first | `revalidateTag(tag, { expire: 0 })` in `/api/revalidate` only |
| `pages/`, `getServerSideProps`, `getStaticProps`, `middleware.ts` | Pages Router; `middleware.ts` is `proxy.ts` in Next.js 16 [spec] | App Router conventions |
| Secrets in `NEXT_PUBLIC_*` env vars | Inlined into the client bundle [spec] | Unprefixed env vars read in `lib/api/` or `lib/auth.ts` |
| `await params` / `cookies()` at the top of a layout | Blocks the static shell for all children | Await inside a component in `<Suspense>` |

## Example

Episode page: cached body, cheer section per request.

```tsx
// app/works/[workId]/episodes/[episodeId]/page.tsx
import { Suspense } from "react";
import { notFound } from "next/navigation";
import { getPublishedEpisode } from "@/lib/api/episodes";
import { SectionErrorBoundary } from "@/components/section-error-boundary";
import { CheerSection } from "./cheer-section";

type Props = PageProps<"/works/[workId]/episodes/[episodeId]">;

// page は境界を並べるだけ。params は Suspense の中で await する
export default function EpisodePage({ params }: Props) {
  return (
    <main>
      <Suspense fallback={<p>読み込み中…</p>}>
        <EpisodeBody params={params} />
      </Suspense>
      <SectionErrorBoundary message="応援を表示できませんでした">
        <Suspense fallback={null}>
          <CheerSection params={params} />
        </Suspense>
      </SectionErrorBoundary>
    </main>
  );
}

async function EpisodeBody({ params }: Pick<Props, "params">) {
  const { workId, episodeId } = await params;
  const episode = await getPublishedEpisode(workId, episodeId);
  if (!episode) notFound();
  return (
    <article>
      <h1>{episode.title}</h1>
      <p>{episode.body}</p>
    </article>
  );
}
```

```tsx
// app/works/[workId]/episodes/[episodeId]/cheer-section.tsx
import { getEpisodeStats } from "@/lib/api/episodes";
import { CheerButton } from "./cheer-button";

// ユーザーごとの状態を含むのでキャッシュしない
export async function CheerSection({ params }: { params: Promise<{ episodeId: string }> }) {
  const { episodeId } = await params;
  const stats = await getEpisodeStats(episodeId);
  return <CheerButton episodeId={episodeId} count={stats.cheerCount} cheered={stats.cheeredByMe} />;
}
```

```tsx
// app/works/[workId]/episodes/[episodeId]/cheer-button.tsx
"use client";
import { useActionState } from "react";
import { Form } from "@/components/form";
import { toggleCheer } from "./actions";

export function CheerButton(props: { episodeId: string; count: number; cheered: boolean }) {
  const [state, action, pending] = useActionState(toggleCheer, { status: "idle" });
  return (
    <Form action={action}>
      <input type="hidden" name="episodeId" value={props.episodeId} />
      <input type="hidden" name="cheered" value={String(props.cheered)} />
      <button disabled={pending}>{props.cheered ? "応援済み" : "応援する"} {props.count}</button>
      {state.status === "error" && <p role="alert">{state.message}</p>}
    </Form>
  );
}
```

```ts
// app/works/[workId]/episodes/[episodeId]/actions.ts
"use server";
import { refresh } from "next/cache";
import { apiFetchAsUser } from "@/lib/api/client";
import type { ActionState } from "@/lib/action-state";
import { isUuid } from "@/lib/uuid";

// 誰でも任意の引数で呼べる前提で、形だけ検証する。認可は Laravel
export async function toggleCheer(_: ActionState, form: FormData): Promise<ActionState> {
  const episodeId = form.get("episodeId");
  if (typeof episodeId !== "string" || !isUuid(episodeId)) return { status: "error", message: "不正なリクエストです" };
  const method = form.get("cheered") === "true" ? "DELETE" : "PUT";
  const res = await apiFetchAsUser(`/episodes/${episodeId}/cheer`, { method });
  if (!res.ok) return { status: "error", message: "応援できませんでした" };
  refresh();
  return { status: "ok" };
}
```

```ts
// lib/api/episodes.ts
import "server-only";
import { cacheLife, cacheTag } from "next/cache";
import { apiFetch, apiFetchAsUser } from "./client";

// 公開データ。ユーザー情報を含まないので共有キャッシュに置く
export async function getPublishedEpisode(workId: string, episodeId: string) {
  "use cache";
  cacheTag(`episode:${episodeId}`, `work:${workId}`);
  cacheLife("days");
  const res = await apiFetch(`/works/${workId}/episodes/${episodeId}`);
  if (res.status === 404) return null;
  const { data } = await res.json();
  return { title: data.title as string, body: data.body as string };
}

export async function getEpisodeStats(episodeId: string) {
  const res = await apiFetchAsUser(`/episodes/${episodeId}/stats`);
  const { data } = await res.json();
  return { cheerCount: data.cheer_count as number, cheeredByMe: data.cheered_by_me as boolean };
}
```

The `as` casts stand in for the generated `schema.d.ts` types until they exist; real code types the JSON from `schema.d.ts`.

## Review checklist

1. Does every changed route still pass `npm run build`? (catches missing `<Suspense>` and `server-only` imports from the client)
2. Is `page.tsx` free of `"use client"` and of top-level `await` on `params`, `searchParams`, `cookies()`, `headers()`?
3. Is every `"use client"` file a leaf that needs state, effects, or events?
4. Does every call to Laravel go through `lib/api/`, and does every file there start with `import "server-only";`? (`fetch` outside `lib/api/client.ts` fails `npm run lint`)
5. Does every `"use cache"` function use only `apiFetch`, and receive only IDs as arguments?
6. Does every cached function call `cacheTag` with the tags from the table above and `cacheLife("days")`?
7. Is per-user data (account, cheer, rating, `/my/*`) read without `"use cache"` and inside `<Suspense>`?
8. Is each per-request section on a public page wrapped in `SectionErrorBoundary`?
9. Do Client Components receive only narrow types or primitives, never API responses?
10. Is there no `fetch` or data-reading action inside `useEffect`?
11. Does every Server Action validate argument shapes before calling Laravel?
12. Do form actions return `ActionState` and map 422 `errors` to `fieldErrors`?
13. Does a successful write call `refresh()`, and do actions avoid `revalidateTag`/`updateTag`?
14. Is the token cookie set or deleted only in the login, signup, demo-start, and logout actions and on 401 in actions?
15. Are there no `export const revalidate|dynamic|fetchCache`, no `middleware.ts`, no `pages/`? (grep)
16. Are there no secrets in `NEXT_PUBLIC_*`? (grep `.env*` and code)
17. Is every new file in the folder of its only user, with no role folders added?
18. Does each new pure function in `lib/` have a colocated `*.test.ts`?
