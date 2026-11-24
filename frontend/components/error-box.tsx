import styles from "./error-box.module.css";

// フォームの上に出す失敗の知らせ。本家のログインのエラーと同じ形。
// ログインは全ての欄のエラーをまとめて渡すので、同じ文言が複数の欄から来たら 1 回だけ出す
export function ErrorBox({ messages }: { messages: string[] }) {
  const unique = [...new Set(messages)];
  if (unique.length === 0) return null;
  return (
    <div role="alert" className={styles.box}>
      {unique.map((message) => (
        <p key={message}>{message}</p>
      ))}
    </div>
  );
}
