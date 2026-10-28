import type { Metadata } from "next";
import type { PublishedWork } from "@/lib/api/works";
import { summarize } from "./summarize";

const DESCRIPTION_MAX = 120;

export function workMetadata(work: PublishedWork): Metadata {
  const description = work.catchphrase ?? summarize(work.introduction, DESCRIPTION_MAX);
  return {
    title: work.title,
    description,
    openGraph: {
      title: work.title,
      description,
      type: "book",
      // layout の metadataBase(SITE_URL)と合わせて絶対 URL になる
      url: `/works/${work.id}`,
    },
  };
}
