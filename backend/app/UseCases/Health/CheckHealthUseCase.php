<?php

namespace App\UseCases\Health;

use App\Domain\Health\CheckResult;
use App\Domain\Health\DependencyChecker;

final readonly class CheckHealthUseCase
{
    public function __construct(private DependencyChecker $checker) {}

    /**
     * @return array{database: CheckResult, redis: CheckResult}
     */
    public function __invoke(): array
    {
        return [
            'database' => $this->checker->database(),
            'redis' => $this->checker->redis(),
        ];
    }
}
