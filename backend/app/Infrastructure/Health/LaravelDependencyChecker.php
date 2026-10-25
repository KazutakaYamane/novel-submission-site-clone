<?php

namespace App\Infrastructure\Health;

use App\Domain\Health\CheckResult;
use App\Domain\Health\DependencyCheckerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class LaravelDependencyChecker implements DependencyCheckerInterface
{
    public function database(): CheckResult
    {
        return $this->check(fn () => DB::select('select 1'));
    }

    public function redis(): CheckResult
    {
        return $this->check(fn () => Redis::connection()->ping());
    }

    private function check(callable $probe): CheckResult
    {
        try {
            $probe();

            return new CheckResult(ok: true);
        } catch (Throwable $e) {
            return new CheckResult(ok: false, error: $e::class);
        }
    }
}
