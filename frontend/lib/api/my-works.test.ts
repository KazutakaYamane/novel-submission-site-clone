import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { WorkInput } from "@/lib/work-form";
import { createWork, getMyWork, listMyWorks, updateWork } from "./my-works";

const request = vi.hoisted(() => ({
  cookies: new Map<string, string>(),
}));

vi.mock("next/headers", () => ({
  headers: async () => new Headers(),
  cookies: async () => ({
    get: (name: string) => (request.cookies.has(name) ? { name, value: request.cookies.get(name) } : undefined),
  }),
}));

vi.mock("next/navigation", () => ({
  redirect: (url: string) => {
    throw new Error(`REDIRECT:${url}`);
  },
}));

const WORK_ID = "0192f0a0-0000-7000-8000-000000000001";

const workResource = {
  id: WORK_ID,
  title: "星を継ぐ者たち",
  catchphrase: "最後の星が消える夜に",
  introduction: "あらすじの本文",
  genre: "sf",
  status: "ongoing",
  rating_flags: ["violence"],
  tags: ["宇宙", "群像劇"],
  published_at: null,
  last_episode_published_at: null,
  created_at: "2026-10-01T00:00:00+00:00",
};

const input: WorkInput = {
  title: "星を継ぐ者たち",
  catchphrase: "最後の星が消える夜に",
  introduction: "あらすじの本文",
  genre: "sf",
  status: "ongoing",
  rating_flags: ["violence"],
  tags: ["宇宙", "群像劇"],
};

let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
  request.cookies = new Map([["auth_token", "1|abc"]]);
  vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
  fetchMock = vi.fn();
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

describe("listMyWorks", () => {
  it("トークンを付けて /api/my/works を呼び、タイトルと公開状態を返す", async () => {
    fetchMock.mockResolvedValue(
      Response.json({
        data: [workResource, { ...workResource, id: "w2", title: "第二作", published_at: "2026-10-09T03:00:00+00:00" }],
      }),
    );

    await expect(listMyWorks()).resolves.toEqual([
      { id: WORK_ID, title: "星を継ぐ者たち", published: false },
      { id: "w2", title: "第二作", published: true },
    ]);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("http://nginx:8080/api/my/works");
    expect(new Headers(init.headers).get("Authorization")).toBe("Bearer 1|abc");
  });

  it("401 なら /session-expired へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));
    await expect(listMyWorks()).rejects.toThrow("REDIRECT:/session-expired");
  });
});

describe("getMyWork", () => {
  it("/api/my/works/{id} を呼び、編集画面が使う項目を返す", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: workResource }));

    await expect(getMyWork(WORK_ID)).resolves.toEqual({
      id: WORK_ID,
      title: "星を継ぐ者たち",
      catchphrase: "最後の星が消える夜に",
      introduction: "あらすじの本文",
      genre: "sf",
      status: "ongoing",
      ratingFlags: ["violence"],
      tags: ["宇宙", "群像劇"],
    });
    expect(fetchMock.mock.calls[0][0]).toBe(`http://nginx:8080/api/my/works/${WORK_ID}`);
  });

  it.each([403, 404])("%i なら null を返す", async (status) => {
    fetchMock.mockResolvedValue(Response.json({ message: "x" }, { status }));
    await expect(getMyWork(WORK_ID)).resolves.toBeNull();
  });

  it("UUID でなければ Laravel を呼ばずに null を返す", async () => {
    await expect(getMyWork("../works")).resolves.toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("401 なら /session-expired へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));
    await expect(getMyWork(WORK_ID)).rejects.toThrow("REDIRECT:/session-expired");
  });

  it("それ以外の失敗はエラーにする", async () => {
    fetchMock.mockResolvedValue(new Response(null, { status: 500 }));
    await expect(getMyWork(WORK_ID)).rejects.toThrow("500");
  });
});

describe("createWork", () => {
  it("POST /api/my/works に JSON で送り、作った作品の ID を返す", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: workResource }, { status: 201 }));

    await expect(createWork(input)).resolves.toEqual({ ok: true, data: { id: WORK_ID } });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("http://nginx:8080/api/my/works");
    expect(init.method).toBe("POST");
    expect(new Headers(init.headers).get("Content-Type")).toBe("application/json");
    expect(JSON.parse(init.body)).toEqual(input);
  });

  it("422 の errors を返す。配列の要素のエラー(tags.0)は配列の項目(tags)にまとめる", async () => {
    fetchMock.mockResolvedValue(
      Response.json(
        {
          message: "x",
          errors: {
            title: ["タイトルを入力してください"],
            "tags.0": ["タグは20文字以下にしてください"],
            "tags.2": ["タグは20文字以下にしてください"],
          },
        },
        { status: 422 },
      ),
    );

    await expect(createWork(input)).resolves.toEqual({
      ok: false,
      reason: "invalid",
      fieldErrors: {
        title: ["タイトルを入力してください"],
        tags: ["タグは20文字以下にしてください"],
      },
    });
  });

  it("401 なら unauthenticated を返す", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));
    await expect(createWork(input)).resolves.toEqual({ ok: false, reason: "unauthenticated" });
  });

  it("それ以外の失敗はエラーにする", async () => {
    fetchMock.mockResolvedValue(new Response(null, { status: 500 }));
    await expect(createWork(input)).rejects.toThrow("500");
  });
});

describe("updateWork", () => {
  it("PATCH /api/my/works/{id} に JSON で送る", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: workResource }));

    await expect(updateWork(WORK_ID, input)).resolves.toEqual({ ok: true, data: undefined });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(`http://nginx:8080/api/my/works/${WORK_ID}`);
    expect(init.method).toBe("PATCH");
    expect(JSON.parse(init.body)).toEqual(input);
  });

  it.each([403, 404])("%i なら not_found を返す", async (status) => {
    fetchMock.mockResolvedValue(Response.json({ message: "x" }, { status }));
    await expect(updateWork(WORK_ID, input)).resolves.toEqual({ ok: false, reason: "not_found" });
  });
});
