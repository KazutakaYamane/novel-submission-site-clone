export const revalidate = 60;

// 生成時刻が複数の ECS タスクで同じなら、ISR キャッシュが Redis で共有されている
export default function IsrPage() {
  return (
    <main>
      <h1>ISR 確認(revalidate: 60 秒)</h1>
      <p>generated_at: {new Date().toISOString()}</p>
    </main>
  );
}
