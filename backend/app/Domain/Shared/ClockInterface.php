<?php

namespace App\Domain\Shared;

use DateTimeImmutable;

interface ClockInterface
{
    /**
     * 秒未満は切り捨てる。DB の timestamp 列が秒までなので、保存した値と返した値をそろえる
     */
    public function now(): DateTimeImmutable;
}
