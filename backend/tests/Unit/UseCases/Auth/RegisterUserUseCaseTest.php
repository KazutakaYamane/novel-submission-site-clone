<?php

use App\Domain\Auth\AccessTokensInterface;
use App\Domain\Auth\IssuedToken;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\Shared\TransactionInterface;
use App\Domain\User\EmailAlreadyTakenException;
use App\Domain\User\User;
use App\Domain\User\UserCredentials;
use App\Domain\User\UserRepositoryInterface;
use App\UseCases\Auth\RegisterUserUseCase;

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

    $hasher = new class implements PasswordHasherInterface
    {
        public function hash(string $plain): string
        {
            return 'hashed:'.$plain;
        }

        public function check(string $plain, string $hashed): bool
        {
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

    // メモリ上の double には巻き戻す DB がないので、処理をそのまま実行する
    $transaction = new class implements TransactionInterface
    {
        public function run(callable $callback): mixed
        {
            return $callback();
        }
    };

    $this->registerUserUseCase = new RegisterUserUseCase($this->users, $hasher, $this->tokens, $transaction);
});

it('パスワードをハッシュにしてからユーザーを保存する', function () {
    ($this->registerUserUseCase)('山田太郎', 'taro@example.com', 'password123');

    expect($this->users->rows['taro@example.com']['hashedPassword'])->toBe('hashed:password123');
});

it('保存したユーザーのトークンを発行して、ユーザーと一緒に返す', function () {
    $authenticated = ($this->registerUserUseCase)('山田太郎', 'taro@example.com', 'password123');

    expect($authenticated->user->email)->toBe('taro@example.com')
        ->and($this->tokens->issued)->toBe([$authenticated->token->token => $authenticated->user->id]);
});

it('登録済みのメールアドレスなら EmailAlreadyTakenException を投げ、保存もトークンの発行もしない', function () {
    ($this->registerUserUseCase)('山田太郎', 'taro@example.com', 'password123');

    expect(fn () => ($this->registerUserUseCase)('別の人', 'taro@example.com', 'password456'))
        ->toThrow(EmailAlreadyTakenException::class)
        ->and($this->users->rows)->toHaveCount(1)
        ->and($this->tokens->issued)->toHaveCount(1);
});
