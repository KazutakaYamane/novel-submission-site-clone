<?php

namespace App\Domain\User;

use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public DateTimeImmutable $createdAt,
    ) {}
}
