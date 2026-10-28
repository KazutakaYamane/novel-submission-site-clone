"use client";
import { startTransition, useSyncExternalStore, type ComponentProps, type FormEvent } from "react";

const subscribe = () => () => {};

type FormProps = Omit<ComponentProps<"form">, "action" | "onSubmit"> & {
  action: (formData: FormData) => void;
};

// <form action> に渡すと、React は action の結果に関係なく送信のたびに非制御の入力をリセットする。
// 422 で入力が消えないよう、onSubmit で送信を止めて action を自分で呼ぶ
export function Form({ action, children, ...props }: FormProps) {
  const hydrated = useSyncExternalStore(
    subscribe,
    () => true,
    () => false,
  );

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    // 押したボタンの name・value(話のエディタの intent)を FormData に入れる
    const submitter = (event.nativeEvent as SubmitEvent).submitter;
    const formData = new FormData(event.currentTarget, submitter);
    startTransition(() => action(formData));
  }

  // ハイドレーション前は onSubmit が無く、送信すると入力値を URL に載せた素の GET になるので、入力も送信も止める。
  // E2E は data-hydrated を待ってから操作する
  return (
    <form {...props} onSubmit={handleSubmit} data-hydrated={hydrated || undefined}>
      <fieldset disabled={!hydrated} style={{ border: 0, padding: 0, margin: 0 }}>
        {children}
      </fieldset>
    </form>
  );
}
