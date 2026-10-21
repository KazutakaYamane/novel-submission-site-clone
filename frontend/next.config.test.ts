import { expect, it } from "vitest";
import nextConfig from "./next.config";

// backend/config/work.php の episode_body_max_bytes。Laravel が上限を判定するので、Next.js はそれより先に断らない
const EPISODE_BODY_MAX_BYTES = 6990474;
// multipart/form-data の区切りや項目名と、タイトルなど本文以外の項目の分
const FORM_OVERHEAD_BYTES = 64 * 1024;

it("Server Action は上限ちょうどの話の本文を含むフォームを受け取れる", () => {
  const limit = nextConfig.experimental?.serverActions?.bodySizeLimit;

  expect(limit).toBeTypeOf("number");
  expect(limit as number).toBeGreaterThanOrEqual(EPISODE_BODY_MAX_BYTES + FORM_OVERHEAD_BYTES);
});
