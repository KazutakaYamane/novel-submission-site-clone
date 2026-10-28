import { afterEach, describe, expect, it, vi } from "vitest";

afterEach(() => {
  vi.unstubAllEnvs();
  vi.resetModules();
});

describe("RootLayout の metadata", () => {
  it("SITE_URL を metadataBase にする", async () => {
    vi.stubEnv("SITE_URL", "https://novel.example.com");
    const { metadata } = await import("./layout");
    expect(metadata.metadataBase?.toString()).toBe("https://novel.example.com/");
  });
});
