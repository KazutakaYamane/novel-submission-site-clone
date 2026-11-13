import Link from "next/link";
import { DemoStartForm } from "@/components/demo-start-form";
import { SiteHeader } from "@/components/site-header";

// 日次リセットでトークンが消えたユーザーが主に来るので、ログインよりデモの始め直しを先に出す
export default function SessionExpiredPage() {
  return (
    <>
      <SiteHeader />
      <main>
        <h1>ログインの有効期限が切れました</h1>
        <p>データは毎日 4:00 にリセットされます。デモのユーザーと作品もリセットで消えるので、もう一度デモを始めてください。</p>
        <DemoStartForm variant="page" />
        <p>
          会員登録したユーザーは、<Link href="/login">ログインする</Link>
        </p>
      </main>
    </>
  );
}
