import styles from "./section-heading.module.css";

// 本家の区切り見出し。1 文字ずつ枠で囲むために文字を分けるので、読み上げの名前は aria-label で 1 語にする
export function SectionHeading({ id, title, description }: { id: string; title: string; description: string }) {
  return (
    <div className={styles.block}>
      <h2 id={id} aria-label={title} className={styles.title}>
        {Array.from(title, (char, i) => (
          <span key={i} aria-hidden="true" className={styles.char}>
            {char}
          </span>
        ))}
      </h2>
      <p className={styles.description}>{description}</p>
    </div>
  );
}
