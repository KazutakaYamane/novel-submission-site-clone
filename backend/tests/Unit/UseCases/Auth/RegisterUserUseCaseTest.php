<?php

use App\Domain\Shared\PasswordHasher;
use App\Domain\User\EmailAlreadyTaken;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\UseCases\Auth\RegisterUserUseCase;

beforeEach(function () {
    $this->users = new class implements UserRepository
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

    $hasher = new class implements PasswordHasher
    {
        public function hash(string $plain): string
        {
            return 'hashed:'.$plain;
        }
    };

    $this->registerUserUseCase = new RegisterUserUseCase($this->users, $hasher);
});

it('パスワードをハッシュにしてからユーザーを保存する', function () {
    $user = ($this->registerUserUseCase)('山田太郎', 'taro@example.com', 'password123');

    expect($user->email)->toBe('taro@example.com')
        ->and($this->users->rows['taro@example.com']['hashedPassword'])->toBe('hashed:password123');
});

it('登録済みのメールアドレスなら EmailAlreadyTaken を投げ、保存しない', function () {
    ($this->registerUserUseCase)('山田太郎', 'taro@example.com', 'password123');

    expect(fn () => ($this->registerUserUseCase)('別の人', 'taro@example.com', 'password456'))
        ->toThrow(EmailAlreadyTaken::class)
        ->and($this->users->rows)->toHaveCount(1);
});
