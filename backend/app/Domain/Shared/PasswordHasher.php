<?php

namespace App\Domain\Shared;

interface PasswordHasher
{
    public function hash(string $plain): string;

    public function check(string $plain, string $hashed): bool;
}
