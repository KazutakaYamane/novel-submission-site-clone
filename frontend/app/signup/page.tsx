import Link from "next/link";
import { Suspense } from "react";
import { RedirectIfLoggedIn } from "@/components/redirect-if-logged-in";
import { SignupForm } from "./signup-form";

export default function SignupPage() {
  return (
    <main>
      <h1>会員登録</h1>
      <Suspense fallback={null}>
        <RedirectIfLoggedIn />
      </Suspense>
      <SignupForm />
      <p>
        <Link href="/login">ログインはこちら</Link>
      </p>
    </main>
  );
}
