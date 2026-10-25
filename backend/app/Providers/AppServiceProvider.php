<?php

namespace App\Providers;

use App\Domain\Auth\AccessTokensInterface;
use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\Health\DependencyCheckerInterface;
use App\Domain\Shared\ClockInterface;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\Shared\TransactionInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Work\WorkRepositoryInterface;
use App\Infrastructure\Clock\LaravelClock;
use App\Infrastructure\Database\DatabaseTransaction;
use App\Infrastructure\Eloquent\EloquentServiceProvider;
use App\Infrastructure\Eloquent\Repositories\EloquentEpisodeRepository;
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
        UserRepositoryInterface::class => EloquentUserRepository::class,
        WorkRepositoryInterface::class => EloquentWorkRepository::class,
        EpisodeRepositoryInterface::class => EloquentEpisodeRepository::class,
        PasswordHasherInterface::class => LaravelPasswordHasher::class,
        DependencyCheckerInterface::class => LaravelDependencyChecker::class,
        AccessTokensInterface::class => SanctumAccessTokens::class,
        TransactionInterface::class => DatabaseTransaction::class,
        ClockInterface::class => LaravelClock::class,
    ];

    public function register(): void
    {
        $this->app->register(EloquentServiceProvider::class);

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
