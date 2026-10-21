import "server-only";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";

const TOKEN_COOKIE = "auth_token";

export async function readToken(): Promise<string | undefined> {
  return (await cookies()).get(TOKEN_COOKIE)?.value;
}

// ログイン・登録・ログアウトの action と、action の中で 401 を受けたときだけ呼ぶ
export async function setToken(token: string, expiresAt: string): Promise<void> {
  const maxAge = Math.max(0, Math.floor((Date.parse(expiresAt) - Date.now()) / 1000));
  (await cookies()).set(TOKEN_COOKIE, token, {
    httpOnly: true,
    // 開発環境は http://localhost・http://web:3000 で動くため secure を外す
    secure: process.env.NODE_ENV !== "development",
    sameSite: "lax",
    path: "/",
    maxAge,
  });
}

export async function deleteToken(): Promise<void> {
  (await cookies()).delete(TOKEN_COOKIE);
}

export async function redirectUnauthenticated(): Promise<never> {
  redirect(unauthenticatedPath(await readToken()));
}

// Server Action の中で 401 を受けたとき。Server Component では cookie を消せないので、ここで消す
export async function endSessionAndRedirect(): Promise<never> {
  const token = await readToken();
  await deleteToken();
  redirect(unauthenticatedPath(token));
}

function unauthenticatedPath(token: string | undefined): string {
  return token ? "/session-expired" : "/login";
}
