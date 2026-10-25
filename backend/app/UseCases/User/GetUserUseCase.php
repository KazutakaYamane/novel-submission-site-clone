<?php

namespace App\UseCases\User;

use App\Domain\User\User;
use App\Domain\User\UserNotFoundException;
use App\Domain\User\UserRepositoryInterface;

final readonly class GetUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @throws UserNotFoundException
     */
    public function __invoke(string $id): User
    {
        return $this->users->findById($id) ?? throw new UserNotFoundException;
    }
}
