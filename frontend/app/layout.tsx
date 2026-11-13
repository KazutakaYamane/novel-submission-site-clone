import type { Metadata } from "next";
import { Noto_Sans_JP, Noto_Serif_JP } from "next/font/google";
import { SITE_NAME } from "./site";
import { SiteFooter } from "./site-footer";
import { SiteHeader } from "./site-header";
import "./globals.css";
import styles from "./layout.module.css";

// globals.css の font-family の末尾に置き、OS に日本語のフォントが無いときだけ使う。
// 日本語の文字は subsets に無く unicode-range で分割配信されるので、preload しない
const notoSans = Noto_Sans_JP({ variable: "--font-noto-sans-jp", preload: false, adjustFontFallback: false });
const notoSerif = Noto_Serif_JP({ variable: "--font-noto-serif-jp", preload: false, adjustFontFallback: false });

export const metadata: Metadata = {
  // og:url を絶対 URL にするため。本番イメージの next build には SITE_URL が無いので、無いときは Next.js の既定に任せる
  metadataBase: process.env.SITE_URL ? new URL(process.env.SITE_URL) : undefined,
  title: SITE_NAME,
  description: "小説投稿サイトのクローン",
  // 本番は公開されているので、検索結果で本家と取り違えられないよう載せない。
  // robots.txt でクロールを禁止すると、検索エンジンがこの noindex を読めず URL だけが載るので、robots.txt は置かない
  robots: { index: false, follow: false },
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="ja" className={`${notoSans.variable} ${notoSerif.variable}`}>
      <body className={styles.body}>
        <SiteHeader />
        <div className={styles.content}>{children}</div>
        <SiteFooter />
      </body>
    </html>
  );
}
