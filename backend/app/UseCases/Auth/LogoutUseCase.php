<?php

namespace App\UseCases\Auth;

use App\Domain\Auth\AccessTokensInterface;

final readonly class LogoutUseCase
{
    public function __construct(
        private AccessTokensInterface $tokens,
    ) {}

    public function __invoke(string $plainTextToken): void
    {
        $this->tokens->revoke($plainTextToken);
    }
}
