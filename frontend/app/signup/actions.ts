"use server";
import { redirect } from "next/navigation";
import type { ActionState } from "@/lib/action-state";
import { register } from "@/lib/api/auth";
import { setToken } from "@/lib/auth";

// 誰でも任意の引数で呼べる前提で、形だけ検証する。入力規則は Laravel が 422 で返す
export async function signupAction(_: ActionState, form: FormData): Promise<ActionState> {
  const name = stringField(form, "name");
  const email = stringField(form, "email");
  const password = stringField(form, "password");
  if (name === null || email === null || password === null) return { status: "error", message: "不正なリクエストです" };

  const result = await register({ name, email, password });
  if (!result.ok) return { status: "error", fieldErrors: result.errors };
  await setToken(result.token, result.expiresAt);
  redirect("/my/works");
}

// 上限は Laravel の max:255 より緩くし、極端に大きい本文を Laravel へ送らないためだけに置く
const MAX_LENGTH = 1000;

function stringField(form: FormData, name: string): string | null {
  const value = form.get(name);
  return typeof value === "string" && value.length <= MAX_LENGTH ? value : null;
}
