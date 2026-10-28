import { expect, type Page } from "@playwright/test";

// ハイドレーション前に送信すると素のフォーム送信になるため、data-hydrated を待ってから操作する
// クライアント遷移の直後は前のページのフォームも残るので、送信ボタンでフォームを特定する
export async function waitForForm(page: Page, button: string) {
  await expect(page.locator("form[data-hydrated]", { has: page.getByRole("button", { name: button }) })).toBeVisible();
}

export function uniqueEmail(): string {
  return `e2e-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.com`;
}
