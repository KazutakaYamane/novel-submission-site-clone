import { connection } from "next/server";
import { getHealth, type Health } from "@/lib/api/health";
import styles from "./health-status.module.css";

export async function HealthStatus() {
  // / は事前描画されるため、ビルド時ではなくリクエスト時に Laravel を呼ばせる
  await connection();
  let health: Health | undefined;
  let error: unknown;
  try {
    health = await getHealth();
  } catch (e) {
    error = e;
  }
  return (
    <>
      <p data-testid="api-status">API: {health?.status ?? "unreachable"}</p>
      <pre className={styles.json}>{health ? JSON.stringify(health, null, 2) : `API に到達できない: ${String(error)}`}</pre>
    </>
  );
}
