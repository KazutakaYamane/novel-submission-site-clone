---
name: verify
description: Run the app and confirm, with evidence, that the current working-tree change does what it is meant to do — start the stack, exercise the changed behavior through the real UI or API, and collect screenshots, responses and E2E results. Use after code review in `implement-task`, or when asked to verify a change before it is committed.
argument-hint: "[what the change is meant to do, if there is no task file]"
---

Confirm that the change in the working tree works in the running app, and hand the user evidence instead of a claim. Unit tests, static analysis and CI do not replace this: they show the code is consistent, not that the feature works when a user (or Next.js) actually uses it.

Done means: every behavior the change is meant to produce has been exercised in the running app, each has evidence, and nothing it touches is broken. If that cannot be reached, stop and say so; never report a change as verified without evidence.

## 1. Decide what to verify

- Source of intent: the task file's 受け入れ条件 (when called from `implement-task`), otherwise `$ARGUMENTS` or the user's request. Read `git diff` and `git status` to see what actually changed.
- Write a short checklist: for each intended behavior, the user-visible flow or API call that shows it, and what you expect to see. Include the failure paths the change handles (validation error, 403, 404).
- Only intended behaviors go on the list. Verifying the implementation (a function was called, a class exists) is the tests' job.
- If the change has no runtime effect (docs, a test-only change, a CI config), say so and stop; there is nothing to verify.

## 2. Start the stack

- `docker compose up -d --wait`, then confirm both entry points answer: `curl -fsS -o /dev/null http://localhost:${NEXT_PORT:-3000}/` (Next.js) and `curl -fsS -o /dev/null http://localhost:${APP_PORT:-8080}/up` (Laravel).
- After a migration change, run `docker compose exec app php artisan migrate`. After a dependency change, rebuild the affected service.
- If the stack does not come up, that is an environment problem, not a verification failure: report the error output and stop.

## 3. Exercise the change

Pick the check that matches the change:

- **UI**: clear out `e2e/.verify/` (gitignored; an earlier run may have left files in it), then write a throwaway Playwright spec there and run it against the dev server. Reuse `e2e/tests/helpers.ts` (`signUp`, `createWork`, `publishNewEpisode`, `reloadUntil`) for setup. Take screenshots of the changed screens with `page.screenshot({ path: ".verify/evidence/<name>.png", fullPage: true })`, at 1280×800 and 390×844 when layout changed. Pages stream in behind `<Suspense>`, so assert that the content you are checking is visible before each screenshot, or it shows the fallback. Open each screenshot with the Read tool and look at it: a passing assertion does not prove the page looks right.
  - Config `e2e/.verify/playwright.config.ts`:
    ```ts
    import { defineConfig } from "@playwright/test";
    import base from "../playwright.config";
    export default defineConfig({ ...base, testDir: ".", projects: [{ name: "verify", use: { baseURL: "http://web:3000" } }] });
    ```
  - Run: `docker compose run --rm e2e sh -c "npm ci && npx playwright test --config=.verify/playwright.config.ts"`
- **API only**: call Laravel through nginx with `curl -i http://localhost:${APP_PORT:-8080}/...` and keep the status line and body. In production only Next.js calls Laravel, so if the change is meant to show up in the UI, check the UI too.
- **Cached data, cache handlers, `/api/revalidate`**: also run `scripts/e2e-cache.sh`; the dev server does not cache like the production build.
- **Queued jobs, mail**: check the side effect where it lands (`docker compose logs worker`, Mailpit at `http://localhost:${MAILPIT_UI_PORT:-8025}`).

Then run the existing E2E specs for the screens and flows the change touches, plus `tests/smoke.spec.ts`: `docker compose run --rm e2e sh -c "npm ci && npx playwright test --project=dev tests/<spec>.ts tests/smoke.spec.ts"`.

The throwaway spec is evidence for this change, not a regression test. If a check deserves to stay, it belongs in `e2e/tests/` as part of the change itself, written test-first.

## 4. When a check fails

- Find the cause before touching code. If the check itself was wrong (bad selector, wrong expectation of the spec), fix the check and say so.
- If the app is wrong, the tests missed a behavior. Follow "Tests" in `AGENTS.md`: first write a test in the suite that fails for this reason (red), then fix (green), rerun the affected suites, then rerun the failed verification.
- After three rounds of fixing the same failure without it passing, stop. Report what fails, what you tried, and what you think the cause is.

## 5. Report

For each item on the checklist: what you did (command or flow), what you expected, what you saw, and the evidence (screenshot path, status line and body, E2E result line). List the failures you fixed with their cause, and anything you could not verify with the reason.

When called from `implement-task`, append this to the task's 結果. Leave `e2e/.verify/` in place so the user can open the screenshots (Playwright empties `test-results/` on every run, so evidence does not go there); give their paths.
