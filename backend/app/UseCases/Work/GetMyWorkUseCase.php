<?php

namespace App\UseCases\Work;

use App\Domain\Work\Work;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
use App\Domain\Work\WorkRepository;

final readonly class GetMyWorkUseCase
{
    public function __construct(
        private WorkRepository $works,
    ) {}

    /**
     * @throws WorkNotFound
     * @throws WorkAccessDenied
     */
    public function __invoke(string $userId, string $workId): Work
    {
        $work = $this->works->findById($workId) ?? throw new WorkNotFound;
        $work->assertEditableBy($userId);

        return $work;
    }
}
