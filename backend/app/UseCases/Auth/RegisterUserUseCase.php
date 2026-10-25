<?php

namespace App\UseCases\Auth;

use App\Domain\Auth\AccessTokensInterface;
use App\Domain\Auth\AuthenticatedUser;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\Shared\TransactionInterface;
use App\Domain\User\EmailAlreadyTakenException;
use App\Domain\User\UserRepositoryInterface;

final readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private AccessTokensInterface $tokens,
        private TransactionInterface $transaction,
    ) {}

    /**
     * @throws EmailAlreadyTakenException
     */
    public function __invoke(string $name, string $email, string $password): AuthenticatedUser
    {
        if ($this->users->existsByEmail($email)) {
            throw new EmailAlreadyTakenException;
        }

        return $this->transaction->run(function () use ($name, $email, $password) {
            $user = $this->users->create($name, $email, $this->hasher->hash($password));

            return new AuthenticatedUser($user, $this->tokens->issue($user->id));
        });
    }
}
