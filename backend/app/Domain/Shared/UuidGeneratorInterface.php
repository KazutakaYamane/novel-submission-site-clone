<?php

namespace App\Domain\Shared;

interface UuidGeneratorInterface
{
    /**
     * UUIDv4。先頭も乱数なので、先頭の数文字で見分けられる
     */
    public function generate(): string;
}
