<?php

namespace App\UseCases\Auth;

use App\Domain\Auth\AccessTokens;

final readonly class LogoutUseCase
{
    public function __construct(
        private AccessTokens $tokens,
    ) {}

    public function __invoke(string $plainTextToken): void
    {
        $this->tokens->revoke($plainTextToken);
    }
}
