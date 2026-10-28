import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  // og:url を絶対 URL にするため。本番イメージの next build には SITE_URL が無いので、無いときは Next.js の既定に任せる
  metadataBase: process.env.SITE_URL ? new URL(process.env.SITE_URL) : undefined,
  title: "novel-site-clone",
  description: "小説投稿サイトのクローン",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="ja">
      <body>{children}</body>
    </html>
  );
}
