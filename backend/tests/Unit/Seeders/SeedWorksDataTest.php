<?php

use App\Domain\Work\Genre;

// データセットはアプリの起動前に評価されるので、facade や database_path() を使わずに __DIR__ から読む
const SEED_DATA_DIR = __DIR__.'/../../../database/seeders/data';

/**
 * @return array<string, array{array<string, mixed>}>
 */
function seedWorks(): array
{
    $works = [];
    foreach (glob(SEED_DATA_DIR.'/works/*.json') ?: [] as $path) {
        $works[basename($path, '.json')] = [json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)];
    }

    return $works;
}

it('マニフェストの作品ごとに JSON がある', function () {
    $manifest = json_decode((string) file_get_contents(SEED_DATA_DIR.'/aozora-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $slugs = array_column($manifest, 'slug');

    expect(array_keys(seedWorks()))->toEqualCanonicalizing($slugs)
        ->and($slugs)->toHaveCount(count(array_unique($slugs)));
});

it('作品の項目が作品の入力の上限に収まっている', function (array $work) {
    $config = require __DIR__.'/../../../config/work.php';

    expect(mb_strlen($work['title']))->toBeGreaterThan(0)->toBeLessThanOrEqual($config['max_length']['title'])
        ->and(mb_strlen($work['author']))->toBeGreaterThan(0)
        ->and(mb_strlen($work['catchphrase']))->toBeGreaterThan(0)->toBeLessThanOrEqual($config['max_length']['catchphrase'])
        ->and(mb_strlen($work['introduction']))->toBeGreaterThan(0)->toBeLessThanOrEqual($config['max_length']['introduction'])
        ->and(Genre::tryFrom($work['genre']))->not->toBeNull()
        ->and($work['status'])->toBe('completed')
        ->and(count($work['tags']))->toBeGreaterThan(0)->toBeLessThanOrEqual($config['tags']['max_count']);
    foreach ($work['tags'] as $tag) {
        expect(mb_strlen($tag))->toBeGreaterThan(0)->toBeLessThanOrEqual($config['tags']['max_length']);
    }
})->with(seedWorks());

it('話が 1 つ以上あり、各話のタイトルと本文が上限に収まっていて、注記が残っていない', function (array $work) {
    $config = require __DIR__.'/../../../config/work.php';

    expect($work['episodes'])->not->toBeEmpty();
    foreach ($work['episodes'] as $episode) {
        expect(mb_strlen($episode['title']))->toBeGreaterThan(0)->toBeLessThanOrEqual($config['episode_max_length']['title'])
            ->and(trim($episode['body']))->not->toBe('')
            ->and(strlen($episode['body']))->toBeLessThanOrEqual($config['episode_body_max_bytes'])
            ->and($episode['body'])->not->toContain('［＃');
    }
})->with(seedWorks());

// 校正者の行が無い作品もあるので、底本から青空文庫の定型文までを確かめる
it('最後の話の本文の末尾に、空行を挟んで底本から始まるクレジットがある', function (array $work) {
    $lastBody = $work['episodes'][array_key_last($work['episodes'])]['body'];

    expect($lastBody)->toMatch('/\n\n底本：.*\n入力：.*\n青空文庫作成ファイル：\nこのファイルは、インターネットの図書館、青空文庫（https?:\/\/www\.aozora\.gr\.jp\/）で作られました。[^\n]*\z/su');
})->with(seedWorks());
