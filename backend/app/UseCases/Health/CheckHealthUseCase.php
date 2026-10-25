<?php

namespace App\UseCases\Health;

use App\Domain\Health\CheckResult;
use App\Domain\Health\DependencyCheckerInterface;

final readonly class CheckHealthUseCase
{
    public function __construct(private DependencyCheckerInterface $checker) {}

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
