import { AuthLink } from "@/components/auth-link";
import { AuthPanel } from "@/components/auth-panel";
import { DemoStartForm } from "@/components/demo-start-form";
import { SiteHeader } from "@/components/site-header";

// 日次リセットでトークンが消えたユーザーが主に来るので、ログインよりデモの始め直しを先に出す。
// 本家に対応する画面は無いので、ログインの画面と同じ部品で組む
export default function SessionExpiredPage() {
  return (
    <>
      <SiteHeader />
      <main>
        <AuthPanel
          title="ログインの有効期限が切れました"
          description="データは毎日 4:00 にリセットされます。デモのユーザーと作品もリセットで消えるので、もう一度デモを始めてください。会員登録したユーザーは、ログインし直してください。"
        >
          <DemoStartForm variant="auth" />
          <AuthLink href="/login">ログインする</AuthLink>
        </AuthPanel>
      </main>
    </>
  );
}
