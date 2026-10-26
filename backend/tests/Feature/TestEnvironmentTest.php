<?php

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('テストは開発用とは別の _test DB に接続する', function () {
    expect(DB::connection()->getDatabaseName())->toEndWith('_test');
});

// コンテナが渡す redis・smtp・local が phpunit.xml の値に勝つと、テストが開発用の Redis と Mailpit に触れる。
// キューが sync だと、公開のリクエストの中でジョブが動いて Next.js を呼んでしまう
it('テストは開発用の Redis・SMTP を使わず、ジョブは DB に積むだけで、APP_ENV は testing になる', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('queue.default'))->toBe('database')
        ->and(config('mail.default'))->toBe('array');
});

it('Http::fake で決めていない外部へのリクエストは例外になる', function () {
    expect(fn () => Http::get('http://web:3000/api/revalidate'))->toThrow(StrayRequestException::class);
});
