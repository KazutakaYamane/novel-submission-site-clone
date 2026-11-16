import Link from "next/link";
import { SiteContentFrame } from "@/components/site-content-frame";
import { SiteHeader } from "@/components/site-header";

// root layout の直下で描画されるので、(site) の layout の枠を自分で付ける
export default function NotFound() {
  return (
    <>
      <SiteHeader />
      <SiteContentFrame>
        <main>
          <h1>ページが見つかりません</h1>
          <Link href="/">トップへ戻る</Link>
        </main>
      </SiteContentFrame>
    </>
  );
}
