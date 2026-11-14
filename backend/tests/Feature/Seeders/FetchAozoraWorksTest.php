<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

const RASHOMON_CARD_URL = 'https://www.aozora.gr.jp/cards/000879/card127.html';
const RASHOMON_ZIP_URL = 'https://www.aozora.gr.jp/cards/000879/files/127_ruby_150.zip';

// 図書カードの「ファイルのダウンロード」の表と同じく、zip へのリンクは図書カードからの相対パスで書かれている
function aozoraCard(string $zipHref): string
{
    return <<<HTML
        <table class="download">
        <tr><td><a href="./files/127.html">127.html</a></td></tr>
        <tr><td><a href="./files/127_txt_150.zip">127_txt_150.zip</a></td></tr>
        <tr><td><a href="{$zipHref}">127_ruby_150.zip</a></td></tr>
        </table>
        HTML;
}

// 青空文庫の配布物と同じく、Shift_JIS・CRLF のテキストを 1 つ入れた zip を作る
function aozoraZip(string $utf8Text): string
{
    $path = tempnam(sys_get_temp_dir(), 'aozora-test');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('rashomon.txt', mb_convert_encoding(str_replace("\n", "\r\n", $utf8Text), 'SJIS-win', 'UTF-8'));
    $zip->close();
    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

function rashomonText(): string
{
    return <<<'TEXT'
        羅生門
        芥川龍之介

        -------------------------------------------------------
        【テキスト中に現れる記号について】

        《》：ルビ
        -------------------------------------------------------

        　ある日の暮方《くれがた》の事である。※［＃小書き平仮名ん、185-6］
        　下人の行方は、誰も知らない。

        底本：「芥川龍之介全集1」ちくま文庫、筑摩書房
        入力：j.utiyama
        校正：かとうかおり
        TEXT;
}

beforeEach(function () {
    $this->output = sys_get_temp_dir().'/aozora-works-'.uniqid();
    Http::preventStrayRequests();
});

afterEach(function () {
    File::deleteDirectory($this->output);
});

function readWorkJson(string $dir, string $slug): array
{
    return json_decode((string) file_get_contents("{$dir}/{$slug}.json"), true, flags: JSON_THROW_ON_ERROR);
}

function manifestEntry(string $slug): array
{
    $manifest = json_decode(File::get(database_path('seeders/data/aozora-manifest.json')), true);

    return collect($manifest)->firstWhere('slug', $slug);
}

it('図書カードの「ルビあり」zip を取得して変換し、作品の JSON を書く', function () {
    Http::fake([
        RASHOMON_CARD_URL => Http::response(aozoraCard('./files/127_ruby_150.zip')),
        RASHOMON_ZIP_URL => Http::response(aozoraZip(rashomonText())),
    ]);

    $this->artisan('seed-data:aozora', ['--only' => 'rashomon', '--output' => $this->output])
        ->expectsOutput('rashomon: 1話 36文字 〓1件')
        ->assertSuccessful();

    $manifest = manifestEntry('rashomon');
    expect(readWorkJson($this->output, 'rashomon'))->toBe([
        'slug' => 'rashomon',
        'author' => '芥川龍之介',
        'title' => '羅生門',
        'catchphrase' => $manifest['catchphrase'],
        'introduction' => $manifest['introduction'],
        'genre' => 'history',
        'status' => 'completed',
        'tags' => $manifest['tags'],
        'source' => ['card_url' => RASHOMON_CARD_URL, 'zip_url' => RASHOMON_ZIP_URL],
        'episodes' => [[
            'title' => '羅生門',
            'body' => "　ある日の暮方《くれがた》の事である。〓\n　下人の行方は、誰も知らない。\n\n"
                ."底本：「芥川龍之介全集1」ちくま文庫、筑摩書房\n入力：j.utiyama\n校正：かとうかおり",
        ]],
    ]);
});

it('--only を付けると、その作品だけを取得する', function () {
    Http::fake([
        RASHOMON_CARD_URL => Http::response(aozoraCard('./files/127_ruby_150.zip')),
        RASHOMON_ZIP_URL => Http::response(aozoraZip(rashomonText())),
    ]);

    $this->artisan('seed-data:aozora', ['--only' => 'rashomon', '--output' => $this->output])->assertSuccessful();

    Http::assertSentCount(2);
    expect(File::files($this->output))->toHaveCount(1);
});

it('マニフェストに author がある作品は、テキストのヘッダーではなくマニフェストの著者名を書く', function () {
    $cardUrl = manifestEntry('miminashi-hoichi-no-hanashi')['card_url'];
    Http::fake([
        $cardUrl => Http::response(aozoraCard('./files/42927_ruby_15422.zip')),
        dirname($cardUrl).'/files/42927_ruby_15422.zip' => Http::response(aozoraZip(
            "耳無芳一の話\n小泉八雲\n戸川明三訳\n\n　本文。\n\n底本：「小泉八雲集」\n"
        )),
    ]);

    $this->artisan('seed-data:aozora', ['--only' => 'miminashi-hoichi-no-hanashi', '--output' => $this->output])
        ->assertSuccessful();

    expect(readWorkJson($this->output, 'miminashi-hoichi-no-hanashi'))
        ->toHaveKey('author', '小泉八雲(戸川明三 訳)')
        ->toHaveKey('title', '耳無芳一の話');
});

it('図書カードに「ルビあり」zip のリンクが無いと、失敗で終わり JSON を書かない', function () {
    Http::fake([
        RASHOMON_CARD_URL => Http::response('<a href="./files/127_txt_150.zip">127_txt_150.zip</a>'),
    ]);

    $this->artisan('seed-data:aozora', ['--only' => 'rashomon', '--output' => $this->output])
        ->expectsOutputToContain('ルビあり')
        ->assertFailed();

    expect(File::exists("{$this->output}/rashomon.json"))->toBeFalse();
});

it('マニフェストに無い slug を --only に渡すと、取得せずに失敗で終わる', function () {
    Http::fake();

    $this->artisan('seed-data:aozora', ['--only' => 'no-such-work', '--output' => $this->output])->assertFailed();

    Http::assertNothingSent();
});

// 副題がある作品はヘッダーの 2 行目が副題になり、著者名と見分けられない
it('マニフェストに title がある作品は、テキストのヘッダーではなくマニフェストの作品名を書く', function () {
    $cardUrl = manifestEntry('hanshichi-obun-no-tamashii')['card_url'];
    Http::fake([
        $cardUrl => Http::response(aozoraCard('./files/1293_ruby_5382.zip')),
        dirname($cardUrl).'/files/1293_ruby_5382.zip' => Http::response(aozoraZip(
            "半七捕物帳\nお文の魂\n岡本綺堂\n\n　本文。\n\n底本：「時代推理小説　半七捕物帳（一）」\n"
        )),
    ]);

    $this->artisan('seed-data:aozora', ['--only' => 'hanshichi-obun-no-tamashii', '--output' => $this->output])
        ->assertSuccessful();

    expect(readWorkJson($this->output, 'hanshichi-obun-no-tamashii'))
        ->toHaveKey('title', '半七捕物帳 お文の魂')
        ->toHaveKey('author', '岡本綺堂');
});
