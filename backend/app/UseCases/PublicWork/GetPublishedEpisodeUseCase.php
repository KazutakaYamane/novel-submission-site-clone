<?php

namespace App\UseCases\PublicWork;

use App\Domain\Episode\EpisodeNotFoundException;
use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\Episode\PublishedEpisode;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Work\WorkNotFoundException;
use App\Domain\Work\WorkRepositoryInterface;

final readonly class GetPublishedEpisodeUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
        private UserRepositoryInterface $users,
        private EpisodeRepositoryInterface $episodes,
    ) {}

    /**
     * @throws WorkNotFoundException
     * @throws EpisodeNotFoundException
     */
    public function __invoke(string $workId, string $episodeId): PublishedEpisode
    {
        $work = $this->works->findPublished($workId) ?? throw new WorkNotFoundException;
        $episode = $this->episodes->findPublishedInWork($workId, $episodeId) ?? throw new EpisodeNotFoundException;
        // works.user_id の外部キーがあるので作者は必ずいる
        $author = $this->users->findById($work->userId) ?? throw new WorkNotFoundException;
        $neighbors = $this->episodes->findPublishedNeighbors($workId, $episode->position);

        return new PublishedEpisode($episode, $work, $author->name, $neighbors['previous'], $neighbors['next']);
    }
}
