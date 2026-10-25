<?php

namespace App\UseCases\PublicWork;

use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Work\PublishedWork;
use App\Domain\Work\WorkNotFoundException;
use App\Domain\Work\WorkRepositoryInterface;

final readonly class GetPublishedWorkUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
        private UserRepositoryInterface $users,
        private EpisodeRepositoryInterface $episodes,
    ) {}

    /**
     * @throws WorkNotFoundException
     */
    public function __invoke(string $workId): PublishedWork
    {
        $work = $this->works->findPublished($workId) ?? throw new WorkNotFoundException;
        // works.user_id の外部キーがあるので作者は必ずいる
        $author = $this->users->findById($work->userId) ?? throw new WorkNotFoundException;

        return new PublishedWork($work, $author->name, $this->episodes->listPublishedInWork($workId));
    }
}
