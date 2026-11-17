import styles from "./meta-separator.module.css";

// 作品のメタ情報(ジャンル・話数・更新日など)の区切りの「•」。記号なので読み上げさせない
export function MetaSeparator() {
  return (
    <span aria-hidden="true" className={styles.separator}>
      •
    </span>
  );
}
