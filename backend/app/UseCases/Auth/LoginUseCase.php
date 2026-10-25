<?php

namespace App\UseCases\Auth;

use App\Domain\Auth\AccessTokensInterface;
use App\Domain\Auth\AuthenticatedUser;
use App\Domain\Auth\InvalidCredentials;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\User\UserRepositoryInterface;

final readonly class LoginUseCase
{
    // 未登録のメールアドレスでも照合を 1 回走らせ、パスワード誤りと応答時間をそろえる。
    // 照合の時間はハッシュに埋め込まれたコストで決まるので、本番の BCRYPT_ROUNDS(12)と同じコストで作ってある
    private const DUMMY_HASH = '$2y$12$I21MTA0xJ5H/9SIGeOoI.OAcE.63NHLjHC9SuqZfsDgk/YxxUEIDe';

    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private AccessTokensInterface $tokens,
    ) {}

    /**
     * @throws InvalidCredentials
     */
    public function __invoke(string $email, string $password): AuthenticatedUser
    {
        $credentials = $this->users->findCredentialsByEmail($email);
        $matches = $this->hasher->check($password, $credentials->hashedPassword ?? self::DUMMY_HASH);

        if ($credentials === null || ! $matches) {
            throw new InvalidCredentials;
        }

        // 照合とこの取得の間にユーザーが消えた場合も、資格情報の誤りとして扱う
        $user = $this->users->findById($credentials->id) ?? throw new InvalidCredentials;

        return new AuthenticatedUser($user, $this->tokens->issue($user->id));
    }
}
