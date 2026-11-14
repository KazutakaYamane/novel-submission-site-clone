<?php

use Database\Seeders\Aozora\AozoraText;

// 本文の前後に、青空文庫のファイルと同じ形のヘッダー(記号の説明ブロック付き)とフッターを付ける
function aozoraText(string $body): string
{
    return implode("\n", [
        '吾輩は猫である',
        '夏目漱石',
        '',
        '-------------------------------------------------------',
        '【テキスト中に現れる記号について】',
        '',
        '《》：ルビ',
        '（例）吾輩《わがはい》',
        '-------------------------------------------------------',
        '',
        $body,
        '',
        '底本：「夏目漱石全集1」ちくま文庫、筑摩書房',
        '入力：柴田卓治',
        '校正：渡部峰子',
        '',
    ]);
}

function aozoraBody(string $body): string
{
    return AozoraText::parse(aozoraText($body))->episodes[0]['body'];
}

describe('ヘッダー', function () {
    it('1 行目を作品名、2 行目を著者名にし、記号の説明ブロックを本文に入れない', function () {
        $document = AozoraText::parse(aozoraText('　吾輩は猫である。'));

        expect($document->title)->toBe('吾輩は猫である')
            ->and($document->author)->toBe('夏目漱石')
            ->and($document->episodes)->toBe([['title' => '吾輩は猫である', 'body' => '　吾輩は猫である。']]);
    });

    it('説明ブロックが無ければ、最初の空行までをヘッダーにする', function () {
        $document = AozoraText::parse("羅生門\n芥川龍之介\n\n　ある日の暮方の事である。\n");

        expect($document->title)->toBe('羅生門')
            ->and($document->author)->toBe('芥川龍之介')
            ->and($document->episodes)->toBe([['title' => '羅生門', 'body' => '　ある日の暮方の事である。']]);
    });
});

describe('作品名・著者名の外字', function () {
    it('作品名と著者名の外字注記も変換する', function () {
        $document = AozoraText::parse("※［＃「土へん＋竒」、U+57FC、12-3］玉\n※［＃「てへん＋劣」、第3水準1-84-77］田\n\n　本文。\n");

        expect($document->title)->toBe('埼玉')
            ->and($document->author)->toBe('挘田')
            ->and($document->episodes[0]['title'])->toBe('埼玉')
            ->and($document->missingGlyphCount)->toBe(0);
    });

    it('作品名と著者名の前後の空白を除く', function () {
        $document = AozoraText::parse("山月記 \n　中島敦 \n\n　本文。\n");

        expect($document->title)->toBe('山月記')
            ->and($document->author)->toBe('中島敦');
    });
});

describe('フッター', function () {
    it('行頭が「底本：」の行から最後までを credit にし、本文から除く', function () {
        $document = AozoraText::parse(aozoraText('　本文。'));

        expect($document->credit)->toBe("底本：「夏目漱石全集1」ちくま文庫、筑摩書房\n入力：柴田卓治\n校正：渡部峰子")
            ->and($document->episodes[0]['body'])->toBe('　本文。');
    });
});

describe('改行', function () {
    it('CRLF と CR を LF にし、行頭の全角空白を残す', function () {
        $text = str_replace("\n", "\r\n", aozoraText("　一行目。\r　二行目。"));

        $document = AozoraText::parse($text);

        expect($document->title)->toBe('吾輩は猫である')
            ->and($document->episodes[0]['body'])->toBe("　一行目。\n　二行目。")
            ->and($document->credit)->toBe("底本：「夏目漱石全集1」ちくま文庫、筑摩書房\n入力：柴田卓治\n校正：渡部峰子");
    });
});

describe('ルビ', function () {
    it('縦線で始まるルビはそのまま残す', function () {
        expect(aozoraBody('　彼は｜春休み《はるやすみ》に出た。'))->toBe('　彼は｜春休み《はるやすみ》に出た。');
    });

    it('《の直前が漢字ならそのまま残す', function () {
        expect(aozoraBody('　吾輩《わがはい》は猫である。'))->toBe('　吾輩《わがはい》は猫である。')
            ->and(aozoraBody('　人々《ひとびと》が来た。'))->toBe('　人々《ひとびと》が来た。')
            ->and(aozoraBody('　一ヶ《か》所。'))->toBe('　一ヶ《か》所。');
    });

    it('《の直前がカタカナ・ひらがな・英字なら、同じ文字種の連続の前に縦線を入れる', function () {
        expect(aozoraBody('　あのアメリカ《米国》から来た。'))->toBe('　あの｜アメリカ《米国》から来た。')
            ->and(aozoraBody('　猫とひらがな《平仮名》。'))->toBe('　猫｜とひらがな《平仮名》。')
            ->and(aozoraBody('　これはLondon《ロンドン》だ。'))->toBe('　これは｜London《ロンドン》だ。');
    });
});

describe('傍点', function () {
    it('直前の文字列の末尾が注記の文字列と同じなら、カクヨムの傍点にする', function () {
        expect(aozoraBody('　それは嘘［＃「嘘」に傍点］だ。'))->toBe('　それは《《嘘》》だ。');
    });

    it('白ゴマ傍点・丸傍点なども傍点にする', function () {
        expect(aozoraBody('　これ［＃「これ」に白ゴマ傍点］とあれ［＃「あれ」に丸傍点］。'))->toBe('　《《これ》》と《《あれ》》。');
    });

    it('傍線の注記と、直前の文字列の末尾が違う傍点の注記は、注記だけを消す', function () {
        expect(aozoraBody('　それは嘘［＃「嘘」に傍線］だ。'))->toBe('　それは嘘だ。')
            ->and(aozoraBody('　それは嘘［＃「本当」に傍点］だ。'))->toBe('　それは嘘だ。');
    });
});

describe('外字', function () {
    it('Unicode が書かれた外字注記はその文字にする', function () {
        expect(aozoraBody('　※［＃「土へん＋竒」、U+57FC、12-3］玉県。'))->toBe('　埼玉県。');
    });

    it('JIS X 0213 の面区点だけの外字注記(第3水準)はその文字にする', function () {
        $document = AozoraText::parse(aozoraText('　※［＃「てへん＋劣」、第3水準1-84-77］と※［＃「口＋它」、第3水準1-14-88］。'));

        expect($document->episodes[0]['body'])->toBe('　挘と咜。')
            ->and($document->missingGlyphCount)->toBe(0);
    });

    it('第4水準(JIS X 0213 の第2面)の外字注記もその文字にする', function () {
        expect(aozoraBody('　※［＃「一／丂」、第4水準2-1-2］と※［＃「虫＋糸」、第4水準2-94-86］。'))->toBe('　丂と𪚲。');
    });

    it('面区点が書かれていない外字注記は〓にし、件数を数えられる', function () {
        $document = AozoraText::parse(aozoraText('　僕※［＃小書き平仮名ん、185-6］とこ。'));

        expect($document->episodes[0]['body'])->toBe('　僕〓とこ。')
            ->and($document->missingGlyphCount)->toBe(1);
    });
});

describe('記号の外字注記', function () {
    it('水準の書かれていない面区点(JIS X 0208 の記号)はその文字にし、〓に数えない', function () {
        $document = AozoraText::parse(aozoraText('　※［＃米印、1-2-8］と※［＃始め角括弧、1-1-46］。'));

        expect($document->episodes[0]['body'])->toBe('　※と［。')
            ->and($document->missingGlyphCount)->toBe(0);
    });

    // 本文は SJIS-win で UTF-8 にする(FetchAozoraWorks)ので、注記の記号も本文に直接書かれた記号と同じ文字にする
    it('水準の書かれていない記号は、本文の文字コード変換と同じ文字にする', function () {
        expect(aozoraBody('　※［＃波ダッシュ、1-1-33］と※［＃全角マイナス、1-1-61］'))->toBe('　～と－');
    });

    it('水準の書かれていない第2面の面区点は外字として変換せず、〓にする', function () {
        $document = AozoraText::parse(aozoraText('　※［＃「一／丂」、底本では2-1-2］。'));

        expect($document->episodes[0]['body'])->toBe('　〓。')
            ->and($document->missingGlyphCount)->toBe(1);
    });

    it('《はカクヨムのルビにならないよう、縦線を前に付ける', function () {
        expect(aozoraBody('　※［＃始め二重山括弧、1-1-52］本※［＃終わり二重山括弧、1-1-53］'))->toBe('　｜《本》');
    });
});

describe('その他の注記', function () {
    it('行内の注記を消す', function () {
        expect(aozoraBody('［＃ここから２字下げ］　本文。［＃地付き］終わり'))->toBe('　本文。終わり');
    });

    it('注記だけの行は行ごと消す', function () {
        expect(aozoraBody("　一行目。\n［＃改ページ］\n　［＃ここで字下げ終わり］\n　二行目。"))->toBe("　一行目。\n　二行目。");
    });
});

describe('くの字点', function () {
    it('／＼を〳〵、／″＼を〴〵にする', function () {
        expect(aozoraBody('　いろいろ／＼とさま／″＼。'))->toBe('　いろいろ〳〵とさま〴〵。');
    });
});

describe('見出し', function () {
    it('「X」は中見出し の形の行で話を区切り、見出しの文字列を話のタイトルにする', function () {
        $document = AozoraText::parse(aozoraText(implode("\n", [
            '［＃５字下げ］一［＃「一」は中見出し］',
            '　一話目。',
            '［＃５字下げ］二［＃「二」は大見出し］',
            '　二話目。',
            '［＃５字下げ］三［＃「三」は小見出し］',
            '　三話目。',
        ])));

        expect($document->episodes)->toBe([
            ['title' => '一', 'body' => '　一話目。'],
            ['title' => '二', 'body' => '　二話目。'],
            ['title' => '三', 'body' => '　三話目。'],
        ]);
    });

    it('中見出し〜中見出し終わり の形の行も見出しにする', function () {
        $document = AozoraText::parse(aozoraText(implode("\n", [
            '［＃中見出し］上［＃中見出し終わり］',
            '　一話目。',
            '［＃大見出し］下［＃大見出し終わり］',
            '　二話目。',
        ])));

        expect($document->episodes)->toBe([
            ['title' => '上', 'body' => '　一話目。'],
            ['title' => '下', 'body' => '　二話目。'],
        ]);
    });

    it('最初の見出しより前の本文は、作品名をタイトルにした 1 話目にする', function () {
        $document = AozoraText::parse(aozoraText("　まえがき。\n［＃中見出し］一［＃中見出し終わり］\n　本文。"));

        expect($document->episodes)->toBe([
            ['title' => '吾輩は猫である', 'body' => '　まえがき。'],
            ['title' => '一', 'body' => '　本文。'],
        ]);
    });

    it('最初の見出しより前が空白だけなら捨てる', function () {
        $document = AozoraText::parse(aozoraText("　\n\n［＃中見出し］一［＃中見出し終わり］\n　本文。"));

        expect($document->episodes)->toBe([['title' => '一', 'body' => '　本文。']]);
    });

    it('見出しだけで本文が空の話は作らない', function () {
        $document = AozoraText::parse(aozoraText("［＃大見出し］第一部［＃大見出し終わり］\n\n［＃中見出し］第一章［＃中見出し終わり］\n　本文。"));

        expect($document->episodes)->toBe([['title' => '第一章', 'body' => '　本文。']]);
    });

    it('見出しの文字列のルビは、話のタイトルに入れない', function () {
        $document = AozoraText::parse(aozoraText("［＃中見出し］序《じょ》と｜終章《しゅうしょう》［＃中見出し終わり］\n　本文。"));

        expect($document->episodes[0]['title'])->toBe('序と終章');
    });

    it('各話の本文の先頭と末尾の空行を除く', function () {
        $document = AozoraText::parse(aozoraText("［＃中見出し］一［＃中見出し終わり］\n\n　\n　一話目。\n\n　続き。\n\n［＃中見出し］二［＃中見出し終わり］\n\n　二話目。\n"));

        expect($document->episodes)->toBe([
            ['title' => '一', 'body' => "　一話目。\n\n　続き。"],
            ['title' => '二', 'body' => '　二話目。'],
        ]);
    });
});

describe('見出しが無い作品', function () {
    it('本文が 10,000 文字以下なら、作品名をタイトルにした 1 話にする', function () {
        $body = str_repeat('あ', 5000)."\n\n".str_repeat('い', 5000);

        $document = AozoraText::parse(aozoraText($body));

        expect($document->episodes)->toBe([['title' => '吾輩は猫である', 'body' => $body]]);
    });

    it('10,000 文字を超えるなら、6,000 文字を超えた後の最初の空行で区切り、タイトルを「その一」から付ける', function () {
        $line = str_repeat('あ', 1000);
        $first = implode("\n", [$line, $line, '', $line, $line, $line, $line, $line]);
        $second = implode("\n", [$line, $line, $line, $line, $line]);

        $document = AozoraText::parse(aozoraText($first."\n\n".$second));

        expect($document->episodes)->toBe([
            ['title' => 'その一', 'body' => $first],
            ['title' => 'その二', 'body' => $second],
        ]);
    });

    it('6,000 文字を超えた後に空行が無ければ、超えた行の行末で区切る', function () {
        $line = str_repeat('あ', 1000);
        $first = implode("\n", array_fill(0, 7, $line));
        $second = implode("\n", array_fill(0, 5, $line));

        $document = AozoraText::parse(aozoraText($first."\n".$second));

        expect($document->episodes)->toBe([
            ['title' => 'その一', 'body' => $first],
            ['title' => 'その二', 'body' => $second],
        ]);
    });

    it('十を超える話にも漢数字でタイトルを付ける', function () {
        $document = AozoraText::parse(aozoraText(implode("\n", array_fill(0, 21, str_repeat('あ', 6001)))));

        expect(array_column($document->episodes, 'title'))->toBe([
            'その一', 'その二', 'その三', 'その四', 'その五', 'その六', 'その七', 'その八', 'その九', 'その十',
            'その十一', 'その十二', 'その十三', 'その十四', 'その十五', 'その十六', 'その十七', 'その十八', 'その十九', 'その二十',
            'その二十一',
        ]);
    });
});
