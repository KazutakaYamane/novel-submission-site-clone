// Client Component の useActionState からも参照するので server-only にしない
export type ActionState =
  | { status: "idle" }
  | { status: "ok" }
  | { status: "error"; message?: string; fieldErrors?: Record<string, string[]> };
