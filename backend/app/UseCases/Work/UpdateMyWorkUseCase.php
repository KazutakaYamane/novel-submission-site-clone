<?php

namespace App\UseCases\Work;

use App\Domain\Shared\PublicPageCacheInterface;
use App\Domain\Work\Work;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Domain\Work\WorkRepositoryInterface;

/**
 * @phpstan-import-type WorkChanges from WorkRepositoryInterface
 */
final readonly class UpdateMyWorkUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
        private PublicPageCacheInterface $pageCache,
    ) {}

    /**
     * @param  WorkChanges  $changes
     *
     * @throws WorkNotFoundException
     * @throws WorkAccessDeniedException
     */
    public function __invoke(string $userId, string $workId, array $changes): Work
    {
        $work = $this->works->findById($workId) ?? throw new WorkNotFoundException;
        $work->assertEditableBy($userId);

        // 確認の後に作品が消えていれば、見つからないときと同じに扱う
        $updated = $this->works->update($workId, $changes) ?? throw new WorkNotFoundException;

        if ($updated->publishedAt !== null) {
            $this->pageCache->forgetWork($workId);
        }

        return $updated;
    }
}
