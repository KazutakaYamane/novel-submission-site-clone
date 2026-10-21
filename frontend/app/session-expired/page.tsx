import Link from "next/link";

export default function SessionExpiredPage() {
  return (
    <main>
      <h1>ログインの有効期限が切れました</h1>
      <p>もう一度ログインしてください。</p>
      <p>
        <Link href="/login">ログインする</Link>
      </p>
    </main>
  );
}
