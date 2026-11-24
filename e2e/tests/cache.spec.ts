import { expect, test as base, type Page } from "@playwright/test";
import { createWork, startDemoAuthor, notFoundHeading, publishNewEpisode, reloadUntil, waitForForm } from "./helpers";

// 本番ビルドの web-prod を相手に、キャッシュしたゲスト向けのページに公開と非公開化が反映されることを確かめる。
// scripts/e2e-cache.sh から --project=cache で実行する
const test = base.extend<{ guest: Page }>({
  guest: async ({ browser }, use) => {
    const context = await browser.newContext();
    await use(await context.newPage());
    await context.close();
  },
});

test("公開した話はキャッシュ済みの目次と前の話のページに出て、非公開に戻すと消える", async ({ page, guest }) => {
  await startDemoAuthor(page);
  const editPath = await createWork(page, "キャッシュを確かめる作品");
  const workPath = editPath.replace("/my/works/", "/works/");
  const firstPath = await publishNewEpisode(page, editPath, "第1話 はじまり", "第1話の本文");

  const toc = guest.getByRole("main").getByRole("list");
  const nextLink = guest.getByRole("link", { name: /^次の話/ });

  // ゲストが開いて、作品ページと第 1 話のページをキャッシュに載せる
  await reloadUntil(guest, workPath, () => expect(toc.getByRole("link", { name: "第1話 はじまり" })).toBeVisible({ timeout: 1000 }));
  await reloadUntil(guest, firstPath, () => expect(guest.getByText("第1話の本文")).toBeVisible({ timeout: 1000 }));
  await expect(nextLink).toHaveCount(0);

  const secondPath = await publishNewEpisode(page, editPath, "第2話 つづき", "第2話の本文");

  await reloadUntil(guest, workPath, () => expect(toc.getByRole("link", { name: "第2話 つづき" })).toBeVisible({ timeout: 1000 }));
  await reloadUntil(guest, firstPath, () => expect(nextLink).toHaveText("次の話: 第2話 つづき", { timeout: 1000 }));
  // 非公開化で 404 に変わることを確かめるため、第 2 話のページもキャッシュに載せる
  await reloadUntil(guest, secondPath, () => expect(guest.getByText("第2話の本文")).toBeVisible({ timeout: 1000 }));

  await page.goto(secondPath.replace("/works/", "/my/works/"));
  await waitForForm(page, "非公開に戻す");
  await page.getByRole("button", { name: "非公開に戻す" }).click();
  await expect(page.getByTestId("episode-status")).toHaveText("下書き");

  await reloadUntil(guest, secondPath, () => expect(notFoundHeading(guest)).toBeVisible({ timeout: 1000 }));
  await reloadUntil(guest, workPath, async () => {
    await expect(toc.getByRole("link", { name: "第1話 はじまり" })).toBeVisible({ timeout: 1000 });
    await expect(toc.getByRole("link", { name: "第2話 つづき" })).toHaveCount(0, { timeout: 1000 });
  });
  await reloadUntil(guest, firstPath, async () => {
    await expect(guest.getByText("第1話の本文")).toBeVisible({ timeout: 1000 });
    await expect(nextLink).toHaveCount(0, { timeout: 1000 });
  });
});

test("トップをキャッシュに載せた後に話を公開すると、トップの新着にその作品が出る", async ({ page, guest }) => {
  const title = `トップの反映を確かめる作品 ${Date.now()}`;
  await startDemoAuthor(page);
  const editPath = await createWork(page, title);
  const newWorks = guest.getByRole("region", { name: "新着の更新作品" });

  await reloadUntil(guest, "/", () => expect(newWorks.getByRole("listitem").first()).toBeVisible({ timeout: 1000 }));
  await expect(newWorks.getByRole("link", { name: title })).toHaveCount(0);

  await publishNewEpisode(page, editPath, "第1話", "本文");

  await reloadUntil(guest, "/", () => expect(newWorks.getByRole("link", { name: title })).toBeVisible({ timeout: 1000 }));
  await expect(newWorks.getByRole("link", { name: title })).toHaveAttribute("href", editPath.replace("/my/works/", "/works/"));
});
