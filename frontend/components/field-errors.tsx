import type { ActionState } from "@/lib/action-state";
import { FormMessage } from "./form-message";

export function FieldErrors({ state, name }: { state: ActionState; name: string }) {
  if (state.status !== "error") return null;
  return state.fieldErrors?.[name]?.map((message) => (
    <FormMessage key={message} role="alert">
      {message}
    </FormMessage>
  ));
}
