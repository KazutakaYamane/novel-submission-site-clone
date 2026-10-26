import { createHash, timingSafeEqual } from "node:crypto";

type Deps = {
  secret: string | undefined;
  revalidateTag: (tag: string, profile: { expire: number }) => void;
};

// Laravel の PublicPageCache の実装と取り決めたタグ。ID は Laravel が作る小文字の UUID
const ALLOWED_TAG = /^(top|ranking|(work|episode):[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/;

export async function handleRevalidate(request: Request, { secret, revalidateTag }: Deps): Promise<Response> {
  if (!isAuthorized(request.headers.get("Authorization"), secret)) {
    return new Response(null, { status: 401 });
  }

  const tags = parseTags(await request.text());
  if (tags === null) {
    return new Response(null, { status: 400 });
  }

  // 公開した話を次のリクエストで見せるため、古い内容を返す期間を 0 にする
  for (const tag of tags) revalidateTag(tag, { expire: 0 });
  return Response.json({ revalidated: tags });
}

function isAuthorized(authorization: string | null, secret: string | undefined): boolean {
  if (!secret || !authorization?.startsWith("Bearer ")) return false;
  // timingSafeEqual は長さが違うと throw し、長さも漏れる。ハッシュにして同じ長さで比べる
  return timingSafeEqual(sha256(authorization.slice("Bearer ".length)), sha256(secret));
}

function sha256(value: string): Buffer {
  return createHash("sha256").update(value).digest();
}

function parseTags(text: string): string[] | null {
  let body: unknown;
  try {
    body = JSON.parse(text);
  } catch {
    return null;
  }
  if (typeof body !== "object" || body === null || !("tags" in body)) return null;
  const { tags } = body;
  if (!Array.isArray(tags) || tags.length === 0) return null;
  return tags.every((tag) => typeof tag === "string" && ALLOWED_TAG.test(tag)) ? tags : null;
}
