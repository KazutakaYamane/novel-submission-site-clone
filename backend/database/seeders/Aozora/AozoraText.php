<?php

namespace Database\Seeders\Aozora;

use Illuminate\Support\Str;

final class AozoraText
{
    // PCRE の \p{Hiragana} などは Script_Extensions で判定され「。」「、」にも一致するため、文字コードの範囲で書く。
    // ヶ(U+30F6)はカクヨムの自動ルビが漢字として親文字に含める(frontend/lib/notation.ts)ので、カタカナから外す
    private const HIRAGANA = '\x{3041}-\x{309F}\x{30FC}';

    private const KATAKANA = '\x{30A1}-\x{30F5}\x{30F7}-\x{30FA}\x{30FC}-\x{30FF}\x{31F0}-\x{31FF}\x{FF66}-\x{FF9F}';

    private const LATIN = 'A-Za-z0-9\x{FF10}-\x{FF19}\x{FF21}-\x{FF3A}\x{FF41}-\x{FF5A}';

    // 文字列の末尾にある、同じ文字種(ひらがな・カタカナ・英数字)の連続
    private const KANA_OR_LATIN_RUN = '/(?:['.self::HIRAGANA.']+|['.self::KATAKANA.']+|['.self::LATIN.']+)$/u';

    public static function parse(string $utf8Text): AozoraDocument
    {
        $lines = array_map(self::convertGaiji(...), explode("\n", str_replace(["\r\n", "\r"], "\n", $utf8Text)));
        [$title, $author, $bodyStart] = self::splitHeader($lines);
        [$bodyLines, $credit] = self::splitFooter(array_slice($lines, $bodyStart));
        // 記号の説明ブロックにある外字の例は数えない
        $missingGlyphCount = substr_count(implode("\n", [$title, $author, ...$bodyLines, $credit]), '〓');

        return new AozoraDocument(
            title: $title,
            author: $author,
            episodes: self::splitEpisodes($title, $bodyLines),
            credit: $credit,
            missingGlyphCount: $missingGlyphCount,
        );
    }

    /**
     * @param  list<string>  $lines
     * @return array{string, string, int} 作品名・著者名・本文の最初の行番号
     */
    private static function splitHeader(array $lines): array
    {
        $count = count($lines);
        $i = 2;
        while ($i < $count && Str::trim($lines[$i]) !== '' && ! self::isRule($lines[$i])) {
            $i++;
        }
        while ($i < $count && Str::trim($lines[$i]) === '') {
            $i++;
        }
        if ($i < $count && self::isRule($lines[$i])) {
            $i++;
            while ($i < $count && ! self::isRule($lines[$i])) {
                $i++;
            }
            $i++;
        }

        return [$lines[0], $lines[1] ?? '', min($i, $count)];
    }

    /**
     * @param  list<string>  $lines
     * @return array{list<string>, string} 本文の行と credit
     */
    private static function splitFooter(array $lines): array
    {
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, '底本：')) {
                return [array_slice($lines, 0, $i), self::trimBlankLines(implode("\n", array_slice($lines, $i)))];
            }
        }

        return [$lines, ''];
    }

    private static function isRule(string $line): bool
    {
        return str_starts_with($line, '-----');
    }

    private static function convertGaiji(string $text): string
    {
        return (string) preg_replace_callback('/※［＃([^］]*)］/u', fn (array $m): string => self::gaiji($m[1]), $text);
    }

    private static function gaiji(string $note): string
    {
        if (preg_match('/U\+([0-9A-Fa-f]{4,6})/', $note, $code) === 1) {
            return (string) mb_chr((int) hexdec($code[1]), 'UTF-8');
        }
        // 青空文庫は《》｜［］＃※ などの記法に使う記号を、水準を書かない面区点(JIS X 0208 の文字)で注記する。
        // 第3水準・第4水準(JIS X 0213 だけの文字)は Shift_JIS に無いので〓にする
        if (! str_contains($note, '水準') && preg_match('/(?<![\d-])1-(\d{1,2})-(\d{1,2})\z/', $note, $code) === 1) {
            $char = self::jisX0208((int) $code[1], (int) $code[2]);
            if ($char !== null) {
                // カクヨム記法では縦線の直後の《はルビにならず、文字として表示される
                return $char === '《' ? '｜《' : $char;
            }
        }

        return '〓';
    }

    // JIS X 0208 の区点を Shift_JIS のバイト列に直して UTF-8 に変換する
    private static function jisX0208(int $row, int $cell): ?string
    {
        if ($row < 1 || $row > 94 || $cell < 1 || $cell > 94) {
            return null;
        }
        $lead = (($row + 1) >> 1) + ($row <= 62 ? 0x80 : 0xC0);
        $trail = $row % 2 === 1 ? $cell + ($cell <= 63 ? 0x3F : 0x40) : $cell + 0x9E;
        $char = mb_convert_encoding(chr($lead).chr($trail), 'UTF-8', 'SJIS-win');

        return $char === '' || $char === '?' ? null : $char;
    }

    // 注記だけの行は null を返し、行ごと消す
    private static function convertLine(string $line): ?string
    {
        $text = str_replace(['／″＼', '／＼'], ['〴〵', '〳〵'], $line);
        $text = self::markRubyBase($text);
        $text = self::convertAnnotations($text);

        if ($text !== $line && Str::trim($text) === '' && Str::trim($line) !== '') {
            return null;
        }

        return $text;
    }

    // 青空文庫はひらがな・カタカナ・英字の連続にも縦線なしでルビを掛ける。
    // カクヨムは縦線なしなら漢字だけを親文字にするので、漢字以外の連続には縦線を入れる
    private static function markRubyBase(string $text): string
    {
        return (string) preg_replace_callback(
            '/([^《》]*)(《[^《》]*》)/u',
            function (array $m): string {
                if (str_contains($m[1], '｜')) {
                    return $m[0];
                }

                return preg_replace(self::KANA_OR_LATIN_RUN, '｜$0', $m[1]).$m[2];
            },
            $text,
        );
    }

    // 傍点は直前の文字列を確かめるので、行の左から順に注記を処理する
    private static function convertAnnotations(string $text): string
    {
        $result = '';
        $offset = 0;
        while (preg_match('/［＃([^］]*)］/u', $text, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            [$annotation, $start] = $m[0];
            $result .= substr($text, $offset, $start - $offset);
            $offset = $start + strlen($annotation);

            if (preg_match('/\A「(.+)」に.*傍点\z/u', $m[1][0], $emphasis) === 1 && str_ends_with($result, $emphasis[1])) {
                $result = substr($result, 0, -strlen($emphasis[1])).'《《'.$emphasis[1].'》》';
            }
        }

        return $result.substr($text, $offset);
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{title: string, body: string}>
     */
    private static function splitEpisodes(string $title, array $lines): array
    {
        // 最初の見出しより前の本文は、作品名をタイトルにした話にする
        $sections = [['title' => $title, 'lines' => []]];
        foreach ($lines as $line) {
            $heading = self::heading($line);
            if ($heading !== null) {
                $sections[] = ['title' => $heading, 'lines' => []];

                continue;
            }
            $converted = self::convertLine($line);
            if ($converted !== null) {
                $sections[array_key_last($sections)]['lines'][] = $converted;
            }
        }

        if (count($sections) === 1) {
            $sections = self::splitByLength($title, $sections[0]['lines']);
        }

        $episodes = [];
        foreach ($sections as $section) {
            $body = self::trimBlankLines(implode("\n", $section['lines']));
            // 「第一部」の直後に「第一章」が続くような、見出しだけの話は作らない
            if (Str::trim($body) !== '') {
                $episodes[] = ['title' => $section['title'], 'body' => $body];
            }
        }

        return $episodes;
    }

    private static function heading(string $line): ?string
    {
        if (preg_match('/［＃「([^」]+)」は[大中小]見出し］/u', $line, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/［＃([大中小])見出し］(.+?)［＃\1見出し終わり］/u', $line, $m) === 1) {
            // 話のタイトルは記法を解釈せずに表示するので、ルビを外して親文字だけにする
            return (string) preg_replace(['/《[^《》]*》/u', '/｜/u'], '', $m[2]);
        }

        return null;
    }

    /**
     * 見出しが無い長い作品を、6,000 文字を超えた後の最初の空行(以降に空行が無ければその行末)で区切る
     *
     * @param  list<string>  $lines
     * @return list<array{title: string, lines: list<string>}>
     */
    private static function splitByLength(string $title, array $lines): array
    {
        // 本文の末尾の空行を「以降の空行」と数えないよう、先に除く
        $lines = explode("\n", self::trimBlankLines(implode("\n", $lines)));
        $lengths = array_map(mb_strlen(...), $lines);
        if (array_sum($lengths) <= 10_000) {
            return [['title' => $title, 'lines' => $lines]];
        }

        $blanks = array_map(fn (string $line): bool => Str::trim($line) === '', $lines);
        $lastBlank = array_key_last(array_filter($blanks)) ?? -1;

        $chunks = [];
        $current = [];
        $count = 0;
        foreach ($lines as $i => $line) {
            $isBreak = $blanks[$i] && $count > 6_000;
            if (! $isBreak) {
                $current[] = $line;
                $count += $lengths[$i];
            }
            if ($count > 6_000 && ($isBreak || $i > $lastBlank)) {
                $chunks[] = $current;
                $current = [];
                $count = 0;
            }
        }
        if ($current !== []) {
            $chunks[] = $current;
        }

        return array_map(
            fn (array $chunk, int $i): array => ['title' => 'その'.self::kanjiNumber($i + 1), 'lines' => $chunk],
            $chunks,
            array_keys($chunks),
        );
    }

    // 1〜9999 を「十一」「二十」「百五」のような漢数字にする
    private static function kanjiNumber(int $n): string
    {
        $digits = ['', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
        $result = '';
        foreach ([1000 => '千', 100 => '百', 10 => '十'] as $unit => $name) {
            $digit = intdiv($n, $unit) % 10;
            if ($digit > 0) {
                $result .= ($digit === 1 ? '' : $digits[$digit]).$name;
            }
        }

        return $result.$digits[$n % 10];
    }

    // 先頭と末尾の空白だけの行を除く。本文の 1 行目の字下げ(全角空白)は残す
    private static function trimBlankLines(string $text): string
    {
        return (string) preg_replace(['/\A(?:[ \t\x{3000}]*\n)+/u', '/(?:\n[ \t\x{3000}]*)+\z/u'], '', $text);
    }
}
