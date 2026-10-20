<?php

namespace App\Infrastructure\Sanctum;

use App\Domain\Auth\AccessTokens;
use App\Domain\Auth\IssuedToken;
use App\Infrastructure\Eloquent\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

final class SanctumAccessTokens implements AccessTokens
{
    public function issue(string $userId): IssuedToken
    {
        // Sanctum は created_at + expiration で期限切れを判定するので、応答で返す期限も同じ値から計算する
        $expiresAt = now()->addMinutes(config()->integer('sanctum.expiration'))->toDateTimeImmutable();
        $token = User::findOrFail($userId)->createToken('web', ['*'], $expiresAt);

        return new IssuedToken($token->plainTextToken, $expiresAt);
    }

    public function revoke(string $plainTextToken): void
    {
        PersonalAccessToken::findToken($plainTextToken)?->delete();
    }
}
