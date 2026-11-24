import { Suspense } from "react";
import { AuthPanel } from "@/components/auth-panel";
import { DemoStartForm } from "@/components/demo-start-form";
import { RedirectIfLoggedIn } from "@/components/redirect-if-logged-in";
import { SiteHeader } from "@/components/site-header";

// 会員登録とログインは無効(ADR 0015)。本家と同じ導線でログインを探した面接官に、無効にした理由とデモの入口を示す
export default function LoginPage() {
  return (
    <>
      <SiteHeader />
      <main>
        <Suspense fallback={null}>
          <RedirectIfLoggedIn />
        </Suspense>
        <AuthPanel title="ログイン" description="個人情報を保持しないため、会員登録とログインは無効にしています。">
          <DemoStartForm variant="auth" />
        </AuthPanel>
      </main>
    </>
  );
}
