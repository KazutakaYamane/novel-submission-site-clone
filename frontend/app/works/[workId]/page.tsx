import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { getPublishedWork } from "@/lib/api/works";
import { workMetadata } from "./metadata";
import { WorkDetail } from "./work-detail";

type Props = PageProps<"/works/[workId]">;

// ページと同じキャッシュ関数を呼ぶので、Laravel へのリクエストは 1 回で済む
export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { workId } = await params;
  const work = await getPublishedWork(workId);
  if (!work) notFound();
  return workMetadata(work);
}

// page は境界を並べるだけ。params は Suspense の中で await する
export default function WorkPage({ params }: Props) {
  return (
    <main>
      <Suspense fallback={<p>読み込み中…</p>}>
        <WorkDetail params={params} />
      </Suspense>
    </main>
  );
}
