<?php

namespace App\Domain\Health;

final readonly class CheckResult
{
    public function __construct(
        public bool $ok,
        public ?string $error = null,
    ) {}
}
