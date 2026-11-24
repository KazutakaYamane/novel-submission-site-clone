import { Suspense } from "react";
import { AuthLink } from "@/components/auth-link";
import { AuthPanel } from "@/components/auth-panel";
import { DemoStartForm } from "@/components/demo-start-form";
import { RedirectIfLoggedIn } from "@/components/redirect-if-logged-in";
import { SiteHeader } from "@/components/site-header";
import { LoginForm } from "./login-form";
import styles from "./page.module.css";

export default function LoginPage() {
  return (
    <>
      <SiteHeader />
      <main>
        <Suspense fallback={null}>
          <RedirectIfLoggedIn />
        </Suspense>
        <AuthPanel title="ログイン">
          <LoginForm />
          <p className={styles.demoLabel}>デモのユーザーで始める</p>
          <DemoStartForm variant="auth" />
          <AuthLink href="/signup">会員登録はこちら</AuthLink>
        </AuthPanel>
      </main>
    </>
  );
}
