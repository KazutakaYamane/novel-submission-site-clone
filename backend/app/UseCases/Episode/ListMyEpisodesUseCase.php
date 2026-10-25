<?php

namespace App\UseCases\Episode;

use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\Episode\EpisodeSummary;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
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
     * @throws WorkNotFound
     * @throws WorkAccessDenied
     */
    public function __invoke(string $userId, string $workId): array
    {
        $work = $this->works->findById($workId) ?? throw new WorkNotFound;
        $work->assertEditableBy($userId);

        return $this->episodes->listByWork($workId);
    }
}
