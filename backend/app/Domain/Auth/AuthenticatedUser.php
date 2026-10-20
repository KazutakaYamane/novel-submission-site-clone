<?php

namespace App\Domain\Auth;

use App\Domain\User\User;

final readonly class AuthenticatedUser
{
    public function __construct(
        public User $user,
        public IssuedToken $token,
    ) {}
}
