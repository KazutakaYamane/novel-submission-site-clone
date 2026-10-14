import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
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
