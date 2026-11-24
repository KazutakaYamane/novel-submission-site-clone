import { Suspense } from "react";
import { AuthLink } from "@/components/auth-link";
import { AuthPanel } from "@/components/auth-panel";
import { RedirectIfLoggedIn } from "@/components/redirect-if-logged-in";
import { SiteHeader } from "@/components/site-header";
import { SignupForm } from "./signup-form";

// 本家はメールアドレスを先に送り、名前とパスワードを次の段階で入れる。ここは 1 回の送信で登録するので 1 画面に並べる
export default function SignupPage() {
  return (
    <>
      <SiteHeader />
      <main>
        <Suspense fallback={null}>
          <RedirectIfLoggedIn />
        </Suspense>
        <AuthPanel
          title="メールアドレスで新規登録"
          description="名前・メールアドレス・パスワードを入力してください。"
        >
          <SignupForm />
          <AuthLink href="/login">ログインはこちら</AuthLink>
        </AuthPanel>
      </main>
    </>
  );
}
