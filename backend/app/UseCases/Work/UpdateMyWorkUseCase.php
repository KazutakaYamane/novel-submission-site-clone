<?php

namespace App\UseCases\Work;

use App\Domain\Work\Work;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
use App\Domain\Work\WorkRepositoryInterface;

/**
 * @phpstan-import-type WorkChanges from WorkRepositoryInterface
 */
final readonly class UpdateMyWorkUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
    ) {}

    /**
     * @param  WorkChanges  $changes
     *
     * @throws WorkNotFound
     * @throws WorkAccessDenied
     */
    public function __invoke(string $userId, string $workId, array $changes): Work
    {
        $work = $this->works->findById($workId) ?? throw new WorkNotFound;
        $work->assertEditableBy($userId);

        // 確認の後に作品が消えていれば、見つからないときと同じに扱う
        return $this->works->update($workId, $changes) ?? throw new WorkNotFound;
    }
}
