<?php

namespace App\Domain\Work;

interface MyWorkSummaryRepositoryInterface
{
    /**
     * @return list<MyWorkSummary> 更新日時の新しい順。同じ日時なら後に作った順
     */
    public function listSummariesByUser(string $userId): array;
}
