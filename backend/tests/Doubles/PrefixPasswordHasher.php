<?php

namespace Tests\Doubles;

use App\Domain\Shared\PasswordHasherInterface;

final class PrefixPasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plain): string
    {
        return 'hashed:'.$plain;
    }

    public function check(string $plain, string $hashed): bool
    {
        return $hashed === 'hashed:'.$plain;
    }
}
