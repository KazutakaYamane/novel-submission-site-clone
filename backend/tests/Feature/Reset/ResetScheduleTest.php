<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;

it('app:reset --force を毎日 4:00 Asia/Tokyo に、1 台のサーバーでだけ実行する', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => Str::endsWith((string) $event->command, 'app:reset --force'));

    expect($events)->toHaveCount(1);
    $event = $events->first();
    expect($event->expression)->toBe('0 4 * * *')
        ->and($event->timezone)->toBe('Asia/Tokyo')
        ->and($event->onOneServer)->toBeTrue();
});
