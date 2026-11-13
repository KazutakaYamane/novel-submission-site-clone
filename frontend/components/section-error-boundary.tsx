"use client";
import { catchError } from "next/error";
import styles from "./section-error-boundary.module.css";

// ユーザーごとの部分(ヘッダーのログイン状態など)で Laravel が失敗しても、キャッシュした本文を error.tsx に置き換えない。
// catchError は redirect()・notFound() を捕まえずに通す
function SectionError({ message }: { message: string }) {
  return <p className={styles.message}>{message}</p>;
}

export const SectionErrorBoundary = catchError(SectionError);
