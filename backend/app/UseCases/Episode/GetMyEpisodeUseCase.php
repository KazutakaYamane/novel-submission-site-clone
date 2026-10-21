<?php

namespace App\UseCases\Episode;

use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeNotFound;
use App\Domain\Episode\EpisodeRepository;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
use App\Domain\Work\WorkRepository;

final readonly class GetMyEpisodeUseCase
{
    public function __construct(
        private WorkRepository $works,
        private EpisodeRepository $episodes,
    ) {}

    /**
     * @throws WorkNotFound
     * @throws WorkAccessDenied
     * @throws EpisodeNotFound
     */
    public function __invoke(string $userId, string $workId, string $episodeId): Episode
    {
        // 話より先に作品の権限を確かめ、他人の作品に下書きがあるかどうかを漏らさない
        $work = $this->works->findById($workId) ?? throw new WorkNotFound;
        $work->assertEditableBy($userId);

        return $this->episodes->findInWork($workId, $episodeId) ?? throw new EpisodeNotFound;
    }
}
