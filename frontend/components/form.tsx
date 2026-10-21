"use client";
import { useSyncExternalStore, type ComponentProps } from "react";

const subscribe = () => () => {};

// ハイドレーション前に送信すると素のフォーム送信になるため、E2E は data-hydrated を待ってから操作する
export function Form(props: ComponentProps<"form">) {
  const hydrated = useSyncExternalStore(
    subscribe,
    () => true,
    () => false,
  );
  return <form {...props} data-hydrated={hydrated || undefined} />;
}
