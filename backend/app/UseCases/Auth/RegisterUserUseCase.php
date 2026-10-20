<?php

namespace App\UseCases\Auth;

use App\Domain\Auth\AccessTokens;
use App\Domain\Auth\AuthenticatedUser;
use App\Domain\Shared\PasswordHasher;
use App\Domain\Shared\Transaction;
use App\Domain\User\EmailAlreadyTaken;
use App\Domain\User\UserRepository;

final readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AccessTokens $tokens,
        private Transaction $transaction,
    ) {}

    /**
     * @throws EmailAlreadyTaken
     */
    public function __invoke(string $name, string $email, string $password): AuthenticatedUser
    {
        if ($this->users->existsByEmail($email)) {
            throw new EmailAlreadyTaken;
        }

        return $this->transaction->run(function () use ($name, $email, $password) {
            $user = $this->users->create($name, $email, $this->hasher->hash($password));

            return new AuthenticatedUser($user, $this->tokens->issue($user->id));
        });
    }
}
