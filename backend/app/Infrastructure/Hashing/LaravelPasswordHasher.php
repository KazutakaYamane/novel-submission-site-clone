<?php

namespace App\Infrastructure\Hashing;

use App\Domain\Shared\PasswordHasher;
use Illuminate\Support\Facades\Hash;

final class LaravelPasswordHasher implements PasswordHasher
{
    public function hash(string $plain): string
    {
        return Hash::make($plain);
    }

    public function check(string $plain, string $hashed): bool
    {
        return Hash::check($plain, $hashed);
    }
}
