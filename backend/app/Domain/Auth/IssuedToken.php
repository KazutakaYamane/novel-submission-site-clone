<?php

namespace App\Domain\Auth;

use DateTimeImmutable;

final readonly class IssuedToken
{
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
    ) {}
}
