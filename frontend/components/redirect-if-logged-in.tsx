import { redirect } from "next/navigation";
import { getCurrentUser } from "@/lib/api/me";

// cookie の有無ではなく Laravel に問い合わせる。期限切れの cookie で /my/works と往復しないように
export async function RedirectIfLoggedIn() {
  if (await getCurrentUser()) redirect("/my/works");
  return null;
}
