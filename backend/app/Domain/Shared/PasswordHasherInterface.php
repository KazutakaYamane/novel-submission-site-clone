<?php

namespace App\Domain\Shared;

interface PasswordHasherInterface
{
    public function hash(string $plain): string;

    public function check(string $plain, string $hashed): bool;
}
