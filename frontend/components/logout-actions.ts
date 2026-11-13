"use server";
import { redirect } from "next/navigation";
import type { ActionState } from "@/lib/action-state";
import { logout } from "@/lib/api/auth";
import { deleteToken, endSessionAndRedirect } from "@/lib/auth";

// 401 以外の失敗では cookie を残す。消すと Laravel 側に有効なトークンが残ったまま操作できなくなる
export async function logoutAction(): Promise<ActionState> {
  if ((await logout()) === "unauthenticated") return endSessionAndRedirect();
  await deleteToken();
  redirect("/login");
}
