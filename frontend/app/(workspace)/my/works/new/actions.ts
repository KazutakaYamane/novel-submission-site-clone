"use server";
import { redirect } from "next/navigation";
import type { ActionState } from "@/lib/action-state";
import { failedActionState } from "@/lib/api/my-request";
import { createWork } from "@/lib/api/my-works";
import { parseWorkForm } from "@/lib/work-form";

export async function createWorkAction(_: ActionState, form: FormData): Promise<ActionState> {
  const parsed = parseWorkForm(form);
  if (!parsed.ok) return parsed.state;

  const result = await createWork(parsed.input);
  if (!result.ok) return failedActionState(result);
  redirect(`/my/works/${result.data.id}`);
}
