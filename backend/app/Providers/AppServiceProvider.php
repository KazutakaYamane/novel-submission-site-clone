<?php

namespace App\Providers;

use App\Domain\Health\DependencyChecker;
use App\Domain\Shared\PasswordHasher;
use App\Domain\User\UserRepository;
use App\Infrastructure\Eloquent\Repositories\EloquentUserRepository;
use App\Infrastructure\Hashing\LaravelPasswordHasher;
use App\Infrastructure\Health\LaravelDependencyChecker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepository::class => EloquentUserRepository::class,
        PasswordHasher::class => LaravelPasswordHasher::class,
        DependencyChecker::class => LaravelDependencyChecker::class,
    ];
}
