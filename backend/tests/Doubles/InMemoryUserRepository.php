<?php

namespace Tests\Doubles;

use App\Domain\User\User;
use App\Domain\User\UserCredentials;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

final class InMemoryUserRepository implements UserRepositoryInterface
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
}
