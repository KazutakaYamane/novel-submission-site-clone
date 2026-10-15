<?php

namespace App\Providers;

use App\Domain\Health\DependencyChecker;
use App\Domain\Shared\PasswordHasher;
use App\Domain\User\UserRepository;
use App\Infrastructure\Eloquent\Repositories\EloquentUserRepository;
use App\Infrastructure\Hashing\LaravelPasswordHasher;
use App\Infrastructure\Health\LaravelDependencyChecker;
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
        PasswordHasher::class => LaravelPasswordHasher::class,
        DependencyChecker::class => LaravelDependencyChecker::class,
    ];

    public function boot(): void
    {
        // IP だけで数えると同じ NAT の利用者を巻き込み、email だけだと他人がその人のログインを止められる
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower($request->string('email')->toString()).'|'.$request->ip()));
    }
}
