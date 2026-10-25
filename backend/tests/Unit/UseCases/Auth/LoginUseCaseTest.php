<?php

use App\Domain\Auth\AccessTokensInterface;
use App\Domain\Auth\InvalidCredentials;
use App\Domain\Auth\IssuedToken;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\User\User;
use App\Domain\User\UserCredentials;
use App\Domain\User\UserRepositoryInterface;
use App\UseCases\Auth\LoginUseCase;

beforeEach(function () {
    $this->users = new class implements UserRepositoryInterface
    {
        /** @var array<string, array{user: User, hashedPassword: string}> */
        public array $rows = [];

        public function findById(string $id): ?User
        {
            foreach ($this->rows as $row) {
                if ($row['user']->id === $id) {
                    return $row['user'];
                }
            }

            return null;
        }

        public function findCredentialsByEmail(string $email): ?UserCredentials
        {
            if (! isset($this->rows[$email])) {
                return null;
            }

            return new UserCredentials($this->rows[$email]['user']->id, $this->rows[$email]['hashedPassword']);
        }

        public function existsByEmail(string $email): bool
        {
            return isset($this->rows[$email]);
        }

        public function create(string $name, string $email, string $hashedPassword): User
        {
            $user = new User('id-'.count($this->rows), $name, $email, new DateTimeImmutable);
            $this->rows[$email] = ['user' => $user, 'hashedPassword' => $hashedPassword];

            return $user;
        }
    };

    $this->hasher = new class implements PasswordHasherInterface
    {
        /** @var list<string> check に渡されたハッシュ */
        public array $checked = [];

        public function hash(string $plain): string
        {
            return 'hashed:'.$plain;
        }

        public function check(string $plain, string $hashed): bool
        {
            $this->checked[] = $hashed;

            return $hashed === 'hashed:'.$plain;
        }
    };

    $this->tokens = new class implements AccessTokensInterface
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
    };

    $this->users->create('山田太郎', 'taro@example.com', 'hashed:password123');
    $this->loginUseCase = new LoginUseCase($this->users, $this->hasher, $this->tokens);
});

it('正しい資格情報ならトークンを発行して、ユーザーと一緒に返す', function () {
    $authenticated = ($this->loginUseCase)('taro@example.com', 'password123');

    expect($authenticated->user->email)->toBe('taro@example.com')
        ->and($this->tokens->issued)->toBe([$authenticated->token->token => $authenticated->user->id]);
});

it('パスワードが誤っていれば InvalidCredentials を投げ、トークンを発行しない', function () {
    expect(fn () => ($this->loginUseCase)('taro@example.com', 'wrong-password'))
        ->toThrow(InvalidCredentials::class)
        ->and($this->tokens->issued)->toBeEmpty();
});

it('未登録のメールアドレスでもパスワードを照合してから InvalidCredentials を投げる', function () {
    expect(fn () => ($this->loginUseCase)('nobody@example.com', 'password123'))
        ->toThrow(InvalidCredentials::class)
        ->and($this->hasher->checked)->toHaveCount(1)
        ->and($this->tokens->issued)->toBeEmpty();
});
