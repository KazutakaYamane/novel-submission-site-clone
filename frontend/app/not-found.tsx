import Link from "next/link";
import { SiteHeader } from "@/components/site-header";

export default function NotFound() {
  return (
    <>
      <SiteHeader />
      <main>
        <h1>ページが見つかりません</h1>
        <Link href="/">トップへ戻る</Link>
      </main>
    </>
  );
}
