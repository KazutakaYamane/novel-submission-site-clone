<?php

namespace App\UseCases\Episode;

use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\Episode\EpisodeSummary;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Domain\Work\WorkRepositoryInterface;

final readonly class ListMyEpisodesUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
        private EpisodeRepositoryInterface $episodes,
    ) {}

    /**
     * @return list<EpisodeSummary>
     *
     * @throws WorkNotFoundException
     * @throws WorkAccessDeniedException
     */
    public function __invoke(string $userId, string $workId): array
    {
        $work = $this->works->findById($workId) ?? throw new WorkNotFoundException;
        $work->assertEditableBy($userId);

        return $this->episodes->listByWork($workId);
    }
}
