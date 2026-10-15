<?php

namespace App\UseCases\User;

use App\Domain\User\User;
use App\Domain\User\UserNotFound;
use App\Domain\User\UserRepository;

final readonly class GetUserUseCase
{
    public function __construct(
        private UserRepository $users,
    ) {}

    /**
     * @throws UserNotFound
     */
    public function __invoke(string $id): User
    {
        return $this->users->findById($id) ?? throw new UserNotFound;
    }
}
