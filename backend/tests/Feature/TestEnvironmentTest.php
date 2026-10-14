<?php

use Illuminate\Support\Facades\DB;

it('テストは開発用とは別の _test DB に接続する', function () {
    expect(DB::connection()->getDatabaseName())->toEndWith('_test');
});

// コンテナが渡す redis・smtp・local が phpunit.xml の値に勝つと、テストが開発用の Redis に触れ、
// APP_ENV が testing でなくなって CSRF 検証が有効になる
it('テストは開発用の Redis・SMTP を使わず、APP_ENV は testing になる', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync')
        ->and(config('mail.default'))->toBe('array');
});
