import { afterEach, expect, it, vi } from "vitest";

afterEach(() => {
  vi.unstubAllEnvs();
  vi.resetModules();
});

// Next.js が読むモジュールを単体テストが import しても、Redis に繋いだり BUILD_ID を読んだりしない
it.each(["./default.mjs", "./incremental.mjs"])("%s は import しただけでは、REDIS_URL も BUILD_ID も要らない", async (entry) => {
  vi.stubEnv("REDIS_URL", "");
  vi.stubEnv("NODE_ENV", "production");
  vi.spyOn(process, "cwd").mockReturnValue("/nonexistent");

  await expect(import(entry)).resolves.toHaveProperty("default");
});
