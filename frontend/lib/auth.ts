import "server-only";
import { cookies } from "next/headers";

const TOKEN_COOKIE = "auth_token";

export async function readToken(): Promise<string | undefined> {
  return (await cookies()).get(TOKEN_COOKIE)?.value;
}
