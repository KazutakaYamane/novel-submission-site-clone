<?php

namespace Tests\Doubles;

use App\Domain\Shared\ClockInterface;
use DateTimeImmutable;

final class FixedClock implements ClockInterface
{
    public function __construct(public DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
