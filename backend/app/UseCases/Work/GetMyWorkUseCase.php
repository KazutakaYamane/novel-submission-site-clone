<?php

namespace App\UseCases\Work;

use App\Domain\Work\Work;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Domain\Work\WorkRepositoryInterface;

final readonly class GetMyWorkUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
    ) {}

    /**
     * @throws WorkNotFoundException
     * @throws WorkAccessDeniedException
     */
    public function __invoke(string $userId, string $workId): Work
    {
        $work = $this->works->findById($workId) ?? throw new WorkNotFoundException;
        $work->assertEditableBy($userId);

        return $work;
    }
}
