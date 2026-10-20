<?php

namespace App\Domain\Auth;

interface AccessTokens
{
    public function issue(string $userId): IssuedToken;

    public function revoke(string $plainTextToken): void;
}
