import { DemoStartForm } from "@/components/demo-start-form";
import { getCurrentUser } from "@/lib/api/me";

// ログイン中に押すと、今のトークンがデモのトークンに置き換わって元のアカウントから外れるので、ゲストにだけ出す(ヘッダーと同じ)。
// cookie を読むので、Suspense の中に置く
export async function HeroDemo() {
  const user = await getCurrentUser();
  if (user) return null;
  return <DemoStartForm variant="hero" />;
}
