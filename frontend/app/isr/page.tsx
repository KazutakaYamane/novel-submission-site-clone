export const revalidate = 60;

// 生成時刻が複数の ECS タスクで同じなら、ISR キャッシュが Redis で共有されている
export default function IsrPage() {
  return (
    <main className="mx-auto max-w-2xl p-8">
      <h1 className="text-2xl font-bold">ISR 確認(revalidate: 60 秒)</h1>
      <p className="mt-4 font-mono text-sm">generated_at: {new Date().toISOString()}</p>
    </main>
  );
}
