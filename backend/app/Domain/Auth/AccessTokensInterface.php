<?php

namespace App\Domain\Auth;

interface AccessTokensInterface
{
    public function issue(string $userId): IssuedToken;

    public function revoke(string $plainTextToken): void;
}
