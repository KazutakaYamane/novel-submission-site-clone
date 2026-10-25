<?php

use App\Domain\Shared\Clock;
use Illuminate\Support\Carbon;

it('現在時刻を秒未満を切り捨てて返す', function () {
    $this->travelTo(Carbon::parse('2026-10-22 12:00:00.700000'));

    expect(app(Clock::class)->now()->format('Y-m-d H:i:s.u'))->toBe('2026-10-22 12:00:00.000000');
});
