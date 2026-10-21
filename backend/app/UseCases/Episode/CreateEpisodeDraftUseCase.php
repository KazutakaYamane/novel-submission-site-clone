<?php

namespace App\UseCases\Episode;

use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeRepository;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
use App\Domain\Work\WorkRepository;

final readonly class CreateEpisodeDraftUseCase
{
    public function __construct(
        private WorkRepository $works,
        private EpisodeRepository $episodes,
    ) {}

    /**
     * @throws WorkNotFound
     * @throws WorkAccessDenied
     */
    public function __invoke(string $userId, string $workId, string $title, string $body): Episode
    {
        $work = $this->works->findById($workId) ?? throw new WorkNotFound;
        $work->assertEditableBy($userId);

        // 確認の後に作品が消えていれば、見つからないときと同じに扱う
        return $this->episodes->appendDraft($workId, $title, $body) ?? throw new WorkNotFound;
    }
}
