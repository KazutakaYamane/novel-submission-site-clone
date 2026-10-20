<?php

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\User\EmailAlreadyTaken;
use App\Domain\User\User;
use App\Domain\User\UserCredentials;
use App\Domain\User\UserRepository;
use App\Infrastructure\Eloquent\Models\User as UserModel;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentUserRepository implements UserRepository
{
    public function findById(string $id): ?User
    {
        $model = UserModel::find($id);

        return $model === null ? null : self::toEntity($model);
    }

    public function findCredentialsByEmail(string $email): ?UserCredentials
    {
        $model = UserModel::where('email', $email)->first(['id', 'password']);

        return $model === null ? null : new UserCredentials($model->id, $model->password);
    }

    public function existsByEmail(string $email): bool
    {
        return UserModel::where('email', $email)->exists();
    }

    public function create(string $name, string $email, string $hashedPassword): User
    {
        try {
            $model = UserModel::create([
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // existsByEmail の確認と INSERT の間に同じメールで登録されたとき
            throw new EmailAlreadyTaken(previous: $e);
        }

        return self::toEntity($model);
    }

    private static function toEntity(UserModel $model): User
    {
        return new User(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
