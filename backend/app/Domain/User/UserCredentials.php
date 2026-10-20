<?php

namespace App\Domain\User;

final readonly class UserCredentials
{
    public function __construct(
        public string $id,
        public string $hashedPassword,
    ) {}
}
