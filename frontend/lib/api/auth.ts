import "server-only";
import { apiFetchAsUser } from "./client";
import type { components, operations } from "./schema";

type LoginInput = components["schemas"]["LoginRequest"];
type RegisterInput = components["schemas"]["RegisterRequest"];
type IssuedToken = operations["auth.login"]["responses"][200]["content"]["application/json"];
type ValidationErrors = components["responses"]["ValidationException"]["content"]["application/json"]["errors"];

export type AuthResult =
  | { ok: true; token: string; expiresAt: string }
  | { ok: false; errors: ValidationErrors };

// IP 単位のレート制限に X-User-IP が要るので、未ログインでも apiFetchAsUser で呼ぶ
export async function login(input: LoginInput): Promise<AuthResult> {
  return issueToken("/auth/login", input);
}

export async function register(input: RegisterInput): Promise<AuthResult> {
  return issueToken("/auth/register", input);
}

export async function logout(): Promise<"ok" | "unauthenticated"> {
  const res = await apiFetchAsUser("/auth/logout", { method: "POST" });
  if (res.status === 401) return "unauthenticated";
  if (!res.ok) throw new Error(`/auth/logout が ${res.status} を返した`);
  return "ok";
}

async function issueToken(path: string, input: LoginInput | RegisterInput): Promise<AuthResult> {
  const res = await apiFetchAsUser(path, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(input),
  });
  if (res.status === 422) {
    const { errors } = await res.json();
    return { ok: false, errors };
  }
  if (!res.ok) throw new Error(`${path} が ${res.status} を返した`);
  const body: IssuedToken = await res.json();
  return { ok: true, token: body.token, expiresAt: body.expires_at };
}
