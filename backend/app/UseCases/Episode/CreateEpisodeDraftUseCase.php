<?php

namespace App\UseCases\Episode;

use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Domain\Work\WorkRepositoryInterface;

final readonly class CreateEpisodeDraftUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
        private EpisodeRepositoryInterface $episodes,
    ) {}

    /**
     * @throws WorkNotFoundException
     * @throws WorkAccessDeniedException
     */
    public function __invoke(string $userId, string $workId, string $title, string $body): Episode
    {
        $work = $this->works->findById($workId) ?? throw new WorkNotFoundException;
        $work->assertEditableBy($userId);

        // 確認の後に作品が消えていれば、見つからないときと同じに扱う
        return $this->episodes->appendDraft($workId, $title, $body) ?? throw new WorkNotFoundException;
    }
}
