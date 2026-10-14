<?php

namespace App\Domain\Shared;

interface PasswordHasher
{
    public function hash(string $plain): string;
}
