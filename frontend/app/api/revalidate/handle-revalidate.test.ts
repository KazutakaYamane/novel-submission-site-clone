import { beforeEach, describe, expect, it, vi, type Mock } from "vitest";
import { handleRevalidate } from "./handle-revalidate";

const workId = "0199f1a2-3b4c-7d5e-8f60-718293a4b5c6";
const episodeId = "0199f1a2-3b4c-7d5e-8f60-718293a4b5c7";

let revalidateTag: Mock<(tag: string, profile: { expire: number }) => void>;

beforeEach(() => {
  revalidateTag = vi.fn();
});

function post(body: unknown, authorization?: string) {
  const headers = new Headers({ "Content-Type": "application/json" });
  if (authorization !== undefined) headers.set("Authorization", authorization);
  return new Request("http://web:3000/api/revalidate", {
    method: "POST",
    headers,
    body: typeof body === "string" ? body : JSON.stringify(body),
  });
}

describe("handleRevalidate", () => {
  it("正しいシークレットなら、各タグを即時に失効させて 200 を返す", async () => {
    const tags = ["top", "ranking", `work:${workId}`, `episode:${episodeId}`];

    const res = await handleRevalidate(post({ tags }, "Bearer s3cret"), { secret: "s3cret", revalidateTag });

    expect(res.status).toBe(200);
    expect(revalidateTag.mock.calls).toEqual(tags.map((tag) => [tag, { expire: 0 }]));
  });

  it.each([
    ["シークレットが誤り", "Bearer wrong", "s3cret"],
    ["シークレットの長さが違う", "Bearer s3cret-longer", "s3cret"],
    ["Authorization が無い", undefined, "s3cret"],
    ["Bearer で始まらない", "s3cret", "s3cret"],
    ["REVALIDATE_SECRET が未設定", "Bearer ", undefined],
    ["REVALIDATE_SECRET が空", "Bearer ", ""],
  ])("%s なら 401 を返し、失効させない", async (_, authorization, secret) => {
    const res = await handleRevalidate(post({ tags: ["top"] }, authorization), { secret, revalidateTag });

    expect(res.status).toBe(401);
    expect(revalidateTag).not.toHaveBeenCalled();
  });

  it.each([
    ["許さないタグを含む", { tags: ["top", "user:1"] }],
    ["UUID でない ID", { tags: ["work:123"] }],
    ["大文字の UUID", { tags: [`work:${workId.toUpperCase()}`] }],
    ["タグが空", { tags: [] }],
    ["タグが文字列でない", { tags: [1] }],
    ["tags が無い", {}],
    ["JSON でない", "tags=top"],
  ])("%s なら 400 を返し、どのタグも失効させない", async (_, body) => {
    const res = await handleRevalidate(post(body, "Bearer s3cret"), { secret: "s3cret", revalidateTag });

    expect(res.status).toBe(400);
    expect(revalidateTag).not.toHaveBeenCalled();
  });
});
