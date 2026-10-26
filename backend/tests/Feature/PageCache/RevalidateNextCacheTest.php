<?php

use App\Infrastructure\PageCache\RevalidateNextCache;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.next.url' => 'http://web.test', 'services.next.revalidate_secret' => 'test-secret']);
});

it('Next.js の /api/revalidate にシークレットとタグを POST する', function () {
    Http::fake(['http://web.test/api/revalidate' => Http::response(['revalidated' => ['top']])]);

    (new RevalidateNextCache(['work:w1', 'top']))->handle();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://web.test/api/revalidate'
        && $request->hasHeader('Authorization', 'Bearer test-secret')
        && $request->data() === ['tags' => ['work:w1', 'top']]);
});

it('Next.js がエラーを返したら例外を投げる', function () {
    Http::fake(['*' => Http::response(null, 500)]);

    expect(fn () => (new RevalidateNextCache(['top']))->handle())->toThrow(RequestException::class);
});

it('失敗したら 5・30・120・300 秒あけてリトライし、5 回失敗したら failed_jobs に残す', function () {
    // available_at と比べる now() がずれないよう、時刻を止めてから動かす
    $this->freezeTime();
    Http::fake(['*' => Http::response(null, 500)]);
    RevalidateNextCache::dispatch(['top']);

    $delays = [];
    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $this->artisan('queue:work', ['--once' => true]);
        $job = DB::table('jobs')->sole();
        $delays[] = $job->available_at - now()->getTimestamp();
        $this->travel(301)->seconds();
    }
    $this->artisan('queue:work', ['--once' => true]);

    expect($delays)->toBe([5, 30, 120, 300])
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1);
    Http::assertSentCount(5);
});
