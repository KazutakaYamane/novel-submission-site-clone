import { AuthPanel } from "@/components/auth-panel";
import { DemoStartForm } from "@/components/demo-start-form";
import { SiteHeader } from "@/components/site-header";

// 日次リセットでトークンが消えたユーザーが来る。本家に対応する画面は無いので、ログインの画面と同じ部品で組む
export default function SessionExpiredPage() {
  return (
    <>
      <SiteHeader />
      <main>
        <AuthPanel
          title="ログインの有効期限が切れました"
          description="データは毎日 4:00 にリセットされます。デモのユーザーと作品もリセットで消えるので、もう一度デモを始めてください。"
        >
          <DemoStartForm variant="auth" />
        </AuthPanel>
      </main>
    </>
  );
}
