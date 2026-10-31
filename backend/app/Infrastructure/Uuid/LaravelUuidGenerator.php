<?php

namespace App\Infrastructure\Uuid;

use App\Domain\Shared\UuidGeneratorInterface;
use Illuminate\Support\Str;

final class LaravelUuidGenerator implements UuidGeneratorInterface
{
    public function generate(): string
    {
        // UUIDv7 は先頭が時刻なので、同じ時期に作ったデモユーザーの名前(先頭 4 文字)がそろってしまう
        return Str::uuid()->toString();
    }
}
