import { SiteContentFrame } from "@/components/site-content-frame";

export default function SiteLayout({ children }: LayoutProps<"/">) {
  return <SiteContentFrame>{children}</SiteContentFrame>;
}
