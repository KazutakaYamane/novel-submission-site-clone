import type { ActionState } from "@/lib/action-state";

export function FieldErrors({ state, name }: { state: ActionState; name: string }) {
  if (state.status !== "error") return null;
  return state.fieldErrors?.[name]?.map((message) => (
    <p key={message} role="alert">
      {message}
    </p>
  ));
}
