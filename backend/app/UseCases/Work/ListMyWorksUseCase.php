<?php

namespace App\UseCases\Work;

use App\Domain\Work\MyWorkSummary;
use App\Domain\Work\MyWorkSummaryRepositoryInterface;

final readonly class ListMyWorksUseCase
{
    public function __construct(
        private MyWorkSummaryRepositoryInterface $summaries,
    ) {}

    /**
     * @return list<MyWorkSummary>
     */
    public function __invoke(string $userId): array
    {
        return $this->summaries->listSummariesByUser($userId);
    }
}
