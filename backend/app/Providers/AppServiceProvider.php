<?php

namespace App\Providers;

use App\Domain\Auth\AccessTokens;
use App\Domain\Health\DependencyChecker;
use App\Domain\Shared\PasswordHasher;
use App\Domain\Shared\Transaction;
use App\Domain\User\UserRepository;
use App\Domain\Work\WorkRepository;
use App\Infrastructure\Database\DatabaseTransaction;
use App\Infrastructure\Eloquent\Repositories\EloquentUserRepository;
use App\Infrastructure\Eloquent\Repositories\EloquentWorkRepository;
use App\Infrastructure\Hashing\LaravelPasswordHasher;
use App\Infrastructure\Health\LaravelDependencyChecker;
use App\Infrastructure\Sanctum\SanctumAccessTokens;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepository::class => EloquentUserRepository::class,
        WorkRepository::class => EloquentWorkRepository::class,
        PasswordHasher::class => LaravelPasswordHasher::class,
        DependencyChecker::class => LaravelDependencyChecker::class,
        AccessTokens::class => SanctumAccessTokens::class,
        Transaction::class => DatabaseTransaction::class,
    ];

    public function register(): void
    {
        // Scramble は dev 依存で、本番イメージ(composer install --no-dev)には入らない
        if (class_exists(Scramble::class)) {
            $this->app->register(OpenApiServiceProvider::class);
        }
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower($request->string('email')->toString()).'|'.$request->ip()));
    }
}
