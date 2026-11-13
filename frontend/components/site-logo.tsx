// 本家のロゴと同じ 130×25 の枠に収めた自作のダミー。形と配色は本家のロゴから取らない
export function SiteLogo({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 130 25" aria-hidden="true">
      <path d="M2 5.5 11 3l9 2.5v16L11 19l-9 2.5z" fill="none" stroke="currentColor" strokeWidth="1.6" />
      <path d="M11 3v16" stroke="currentColor" strokeWidth="1.6" />
      <text
        x="25"
        y="17.5"
        textLength="104"
        lengthAdjust="spacingAndGlyphs"
        fill="currentColor"
        fontFamily="Helvetica, Arial, sans-serif"
        fontSize="15"
        fontWeight="700"
      >
        novel-site-clone
      </text>
    </svg>
  );
}
