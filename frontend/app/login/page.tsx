import Link from "next/link";
import { Suspense } from "react";
import { RedirectIfLoggedIn } from "@/components/redirect-if-logged-in";
import { LoginForm } from "./login-form";

export default function LoginPage() {
  return (
    <main>
      <h1>ログイン</h1>
      <Suspense fallback={null}>
        <RedirectIfLoggedIn />
      </Suspense>
      <LoginForm />
      <p>
        <Link href="/signup">会員登録はこちら</Link>
      </p>
    </main>
  );
}
