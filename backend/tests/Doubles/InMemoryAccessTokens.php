<?php

namespace Tests\Doubles;

use App\Domain\Auth\AccessTokensInterface;
use App\Domain\Auth\IssuedToken;
use DateTimeImmutable;

final class InMemoryAccessTokens implements AccessTokensInterface, RollbackableDouble
{
    /** @var array<string, string> 平文トークン => ユーザー ID */
    public array $issued = [];

    public function issue(string $userId): IssuedToken
    {
        $token = 'token-'.count($this->issued);
        $this->issued[$token] = $userId;

        return new IssuedToken($token, new DateTimeImmutable('+7 days'));
    }

    public function revoke(string $plainTextToken): void
    {
        unset($this->issued[$plainTextToken]);
    }

    public function snapshot(): mixed
    {
        return $this->issued;
    }

    public function restore(mixed $snapshot): void
    {
        $this->issued = $snapshot;
    }
}
