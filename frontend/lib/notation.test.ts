import { describe, expect, it } from "vitest";
import { parseLine, stripNotation } from "./notation";

const text = (text: string) => ({ type: "text", text }) as const;
const ruby = (base: string, ruby: string) => ({ type: "ruby", base, ruby }) as const;
const emphasis = (text: string) => ({ type: "emphasis", text }) as const;

describe("parseLine", () => {
  it("記法のない行は 1 つの text になり、空行はトークンを出さない", () => {
    expect(parseLine("吾輩は猫である。")).toEqual([text("吾輩は猫である。")]);
    expect(parseLine("")).toEqual([]);
  });

  it("規則1: 自動ルビの親文字は《の直前に連続する漢字だけ", () => {
    expect(parseLine("今日は東京《とうきょう》へ")).toEqual([
      text("今日は"),
      ruby("東京", "とうきょう"),
      text("へ"),
    ]);
  });

  it("規則1: 親文字が行頭から始まるとき、空の text を出さない", () => {
    expect(parseLine("東京《とうきょう》")).toEqual([ruby("東京", "とうきょう")]);
  });

  it("規則2: 々・〆・ヶ・〇 も漢字として親文字に含める", () => {
    expect(parseLine("佐々木《ささき》")).toEqual([ruby("佐々木", "ささき")]);
    expect(parseLine("〆切《しめきり》")).toEqual([ruby("〆切", "しめきり")]);
    expect(parseLine("一ヶ月《いっかげつ》")).toEqual([ruby("一ヶ月", "いっかげつ")]);
    expect(parseLine("二〇《にじゅう》")).toEqual([ruby("二〇", "にじゅう")]);
  });

  it("規則2: サロゲートペアの漢字を 1 文字の漢字として扱う", () => {
    expect(parseLine("𠮟《しか》る")).toEqual([ruby("𠮟", "しか"), text("る")]);
  });

  it("規則2: 異体字セレクタは漢字として扱わず、自動ルビの漢字の連続はそこで切れる", () => {
    expect(parseLine("葛\u{E0100}城《かつらぎ》")).toEqual([text("葛\u{E0100}"), ruby("城", "かつらぎ")]);
    expect(parseLine("辻︀《つじ》")).toEqual([text("辻︀《つじ》")]);
  });

  it("規則3:《の直前が漢字でなければ、記法の文字のまま text にする", () => {
    expect(parseLine("ひらがな《ルビ》")).toEqual([text("ひらがな《ルビ》")]);
    expect(parseLine("《ルビ》")).toEqual([text("《ルビ》")]);
  });

  it("規則4: 全角・半角の縦線の後ろから《までを親文字にし、漢字以外も親文字にできる", () => {
    expect(parseLine("｜ロンドン《倫敦》")).toEqual([ruby("ロンドン", "倫敦")]);
    expect(parseLine("|ロンドン《倫敦》")).toEqual([ruby("ロンドン", "倫敦")]);
  });

  it("規則4: 縦線があれば、直前の漢字の連続より縦線の位置を優先する", () => {
    expect(parseLine("日本語｜文法《ぶんぽう》")).toEqual([text("日本語"), ruby("文法", "ぶんぽう")]);
  });

  it("規則5: 後ろに《…》が続かない縦線は文字として残す", () => {
    expect(parseLine("a｜b")).toEqual([text("a｜b")]);
    expect(parseLine("a|b")).toEqual([text("a|b")]);
  });

  it("規則5: 縦線が 2 本あれば《に近い縦線が親文字の始まりになり、もう 1 本は文字として残る", () => {
    expect(parseLine("｜あ｜い《う》")).toEqual([text("｜あ"), ruby("い", "う")]);
  });

  it("規則5: ルビにならなかった明示ルビの縦線は文字として残す", () => {
    expect(parseLine("｜あ《》")).toEqual([text("｜あ《》")]);
  });

  it("規則6: 明示ルビの親文字は 20 文字までルビにし、21 文字なら記法の文字のまま出す", () => {
    const base20 = "あ".repeat(20);
    expect(parseLine(`｜${base20}《る》`)).toEqual([ruby(base20, "る")]);
    expect(parseLine(`｜${base20}あ《る》`)).toEqual([text(`｜${base20}あ《る》`)]);
  });

  it("規則6: 明示ルビの親文字が 20 文字を超えるときは、縦線を文字として残し、自動ルビとして《の直前の漢字を親文字にする", () => {
    const kanji21 = "一二三四五六七八九十百千万億兆京垓秭穣溝澗";
    expect(parseLine(`｜${kanji21}《るび》`)).toEqual([text("｜一"), ruby(kanji21.slice(1), "るび")]);
    const hiragana20 = "あ".repeat(20);
    expect(parseLine(`｜${hiragana20}漢字《かんじ》`)).toEqual([text(`｜${hiragana20}`), ruby("漢字", "かんじ")]);
  });

  it("規則6: 親文字の文字数はコードポイント単位で数える", () => {
    const base20 = "𠮟".repeat(20);
    expect(parseLine(`｜${base20}《る》`)).toEqual([ruby(base20, "る")]);
  });

  it("規則6: ルビは 50 文字までルビにし、51 文字なら記法の文字のまま出す", () => {
    const ruby50 = "る".repeat(50);
    expect(parseLine(`漢字《${ruby50}》`)).toEqual([ruby("漢字", ruby50)]);
    expect(parseLine(`漢字《${ruby50}る》`)).toEqual([text(`漢字《${ruby50}る》`)]);
  });

  it("規則6: 自動ルビで直前の漢字が 20 文字を超えるときは、《に近い 20 文字を親文字にする", () => {
    const kanji21 = "一二三四五六七八九十百千万億兆京垓秭穣溝澗";
    expect(parseLine(`${kanji21}《るび》`)).toEqual([text("一"), ruby(kanji21.slice(1), "るび")]);
  });

  it("規則7: 縦線の直後の《は《という文字になり、縦線は消える", () => {
    expect(parseLine("｜《")).toEqual([text("《")]);
    expect(parseLine("|《")).toEqual([text("《")]);
    expect(parseLine("｜《ルビ》ではない")).toEqual([text("《ルビ》ではない")]);
  });

  it("規則7: エスケープした《の直前に漢字があってもルビにしない", () => {
    expect(parseLine("漢字｜《かんじ》")).toEqual([text("漢字《かんじ》")]);
  });

  it("規則8: 《《》》で囲んだ文字を傍点にする", () => {
    expect(parseLine("あ《《傍点》》い")).toEqual([text("あ"), emphasis("傍点"), text("い")]);
  });

  it("規則8: 傍点の中身が空なら記法の文字のまま出す", () => {
    expect(parseLine("《《》》")).toEqual([text("《《》》")]);
  });

  it("規則9: 対応する》が同じ行にない《は記法の文字のまま出す", () => {
    expect(parseLine("漢字《かんじ")).toEqual([text("漢字《かんじ")]);
    expect(parseLine("｜あ《い")).toEqual([text("｜あ《い")]);
  });

  it("規則9: 対応する》》が同じ行にない《《は記法の文字のまま出す", () => {
    expect(parseLine("《《傍点》")).toEqual([text("《《傍点》")]);
  });

  it("規則10: ルビは最初の》で区切り、ルビの中の記法は解釈せず、残りは text にする", () => {
    expect(parseLine("｜a《b《c》》")).toEqual([ruby("a", "b《c"), text("》")]);
  });

  it("規則10: 傍点の中のルビは解釈せず、傍点の中身は text のまま", () => {
    expect(parseLine("《《｜漢字《かんじ》が》》")).toEqual([emphasis("｜漢字《かんじ》が")]);
  });

  it("規則11: ルビが空ならルビにせず記法の文字のまま出す", () => {
    expect(parseLine("漢字《》")).toEqual([text("漢字《》")]);
  });

  // 本文は 1 行に数百万字入りうるので、閉じない記法が多い行でも行の長さに比例する時間で終わること
  it("閉じない《《が多い行でも 1 秒以内に終わる", () => {
    const start = performance.now();
    parseLine("《".repeat(100_000));
    expect(performance.now() - start).toBeLessThan(1000);
  });

  it("閉じない自動ルビの《が多い行でも 1 秒以内に終わる", () => {
    const start = performance.now();
    parseLine("漢《".repeat(100_000));
    expect(performance.now() - start).toBeLessThan(1000);
  });

  it("<・>・& はエスケープせず、そのまま text とルビに入れる", () => {
    expect(parseLine("<b>&amp;漢字《<i>》&")).toEqual([text("<b>&amp;"), ruby("漢字", "<i>"), text("&")]);
  });
});

describe("stripNotation", () => {
  it("ルビは親文字だけ、傍点は中身だけを残し、記法にならない文字はそのまま残す", () => {
    expect(stripNotation("｜ロンドン《倫敦》の《《霧》》と東京《とうきょう》")).toBe("ロンドンの霧と東京");
    expect(stripNotation("ひらがな《ルビ》と｜《")).toBe("ひらがな《ルビ》と《");
  });
});
