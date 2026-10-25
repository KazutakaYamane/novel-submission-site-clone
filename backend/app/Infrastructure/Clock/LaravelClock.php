<?php

namespace App\Infrastructure\Clock;

use App\Domain\Shared\Clock;
use DateTimeImmutable;
use Illuminate\Support\Facades\Date;

final class LaravelClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        // テストの travelTo() で止めた時刻を返すよう、Laravel の Date を通す
        return Date::now()->startOfSecond()->toDateTimeImmutable();
    }
}
