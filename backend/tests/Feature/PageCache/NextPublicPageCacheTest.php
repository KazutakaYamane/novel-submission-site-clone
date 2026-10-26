<?php

use App\Domain\Shared\PublicPageCacheInterface;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// テストのキューは DB なので積んだ行もロールバックされるが、本番の Redis では残る。積んだこと自体を JobQueued で数える
beforeEach(function () {
    $this->queued = 0;
    Event::listen(JobQueued::class, function () {
        $this->queued++;
    });
});

it('トランザクションの中で頼むと、コミットしてから積む', function () {
    $queuedInside = null;

    DB::transaction(function () use (&$queuedInside) {
        app(PublicPageCacheInterface::class)->forgetWork('work-1');
        $queuedInside = $this->queued;
    });

    expect($queuedInside)->toBe(0)
        ->and($this->queued)->toBe(1);
});

it('トランザクションがロールバックしたら積まない', function () {
    try {
        DB::transaction(function () {
            app(PublicPageCacheInterface::class)->forgetEpisode('work-1', 'episode-1');
            throw new RuntimeException;
        });
    } catch (RuntimeException) {
    }

    expect($this->queued)->toBe(0);
});
