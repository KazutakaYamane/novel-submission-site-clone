<?php

namespace App\UseCases\Auth;

use App\Domain\Shared\PasswordHasher;
use App\Domain\User\EmailAlreadyTaken;
use App\Domain\User\User;
use App\Domain\User\UserRepository;

final readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {}

    /**
     * @throws EmailAlreadyTaken
     */
    public function __invoke(string $name, string $email, string $password): User
    {
        if ($this->users->existsByEmail($email)) {
            throw new EmailAlreadyTaken;
        }

        return $this->users->create($name, $email, $this->hasher->hash($password));
    }
}
