import { expect, test as base, type Page } from "@playwright/test";
import { createWork, notFoundHeading, signUp, waitForForm } from "./helpers";

// 本番ビルドの web-prod を相手に、キャッシュしたゲスト向けのページに公開と非公開化が反映されることを確かめる。
// scripts/e2e-cache.sh から --project=cache で実行する
const test = base.extend<{ guest: Page }>({
  guest: async ({ browser }, use) => {
    const context = await browser.newContext();
    await use(await context.newPage());
    await context.close();
  },
});

// 話を追加して本文を書き、公開する。公開ページのパスを返す
async function publishNewEpisode(page: Page, editPath: string, title: string, body: string): Promise<string> {
  await page.goto(editPath);
  await waitForForm(page, "話を追加");
  await page.getByLabel("話のタイトル").fill(title);
  await page.getByRole("button", { name: "話を追加" }).click();
  await expect(page).toHaveURL(new RegExp(`${editPath}/episodes/[0-9a-f-]{36}$`));
  const path = new URL(page.url()).pathname;
  await waitForForm(page, "下書き保存");
  await page.getByLabel("本文").fill(body);
  await page.getByRole("button", { name: "公開" }).click();
  await expect(page.getByTestId("episode-status")).toHaveText("公開中");
  return path.replace("/my/works/", "/works/");
}

// 破棄は worker 経由の非同期なので、反映されるまで開き直す
async function reloadUntil(page: Page, path: string, assertion: () => Promise<void>) {
  await expect(async () => {
    await page.goto(path);
    await assertion();
  }).toPass();
}

test("公開した話はキャッシュ済みの目次と前の話のページに出て、非公開に戻すと消える", async ({ page, guest }) => {
  await signUp(page, "E2E 作者");
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
