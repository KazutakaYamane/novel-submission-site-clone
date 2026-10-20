<?php

namespace App\Domain\User;

interface UserRepository
{
    public function findById(string $id): ?User;

    public function findCredentialsByEmail(string $email): ?UserCredentials;

    public function existsByEmail(string $email): bool;

    /**
     * @throws EmailAlreadyTaken
     */
    public function create(string $name, string $email, string $hashedPassword): User;
}
