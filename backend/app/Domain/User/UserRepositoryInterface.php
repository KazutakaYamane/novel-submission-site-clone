<?php

namespace App\Domain\User;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;

    /**
     * @param  list<string>  $ids
     * @return array<string, string> id => 名前。存在しない id は含まない
     */
    public function findNamesByIds(array $ids): array;

    public function findCredentialsByEmail(string $email): ?UserCredentials;

    public function existsByEmail(string $email): bool;

    /**
     * @throws EmailAlreadyTakenException
     */
    public function create(string $name, string $email, string $hashedPassword): User;
}
