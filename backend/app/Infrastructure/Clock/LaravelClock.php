<?php

namespace App\Infrastructure\Clock;

use App\Domain\Shared\ClockInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\Date;

final class LaravelClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        // テストの travelTo() で止めた時刻を返すよう、Laravel の Date を通す
        return Date::now()->startOfSecond()->toDateTimeImmutable();
    }
}
