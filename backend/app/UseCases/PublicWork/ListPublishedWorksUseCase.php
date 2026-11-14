<?php

namespace App\UseCases\PublicWork;

use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Work\PublishedWorkSummary;
use App\Domain\Work\Work;
use App\Domain\Work\WorkRepositoryInterface;

final readonly class ListPublishedWorksUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
        private UserRepositoryInterface $users,
        private EpisodeRepositoryInterface $episodes,
    ) {}

    /**
     * @return list<PublishedWorkSummary>
     */
    public function __invoke(int $limit): array
    {
        $works = $this->works->listByLastEpisodePublishedAt($limit);
        // 作品ごとに読むとクエリ数が件数に比例するので、一覧の作品の分をまとめて読む
        $authorNames = $this->users->findNamesByIds(array_values(array_unique(array_map(fn (Work $work) => $work->userId, $works))));
        $episodeCounts = $this->episodes->countPublishedByWorks(array_map(fn (Work $work) => $work->id, $works));

        return array_map(
            // works.user_id の外部キーがあるので作者は必ずいる
            fn (Work $work) => new PublishedWorkSummary($work, $authorNames[$work->userId], $episodeCounts[$work->id]),
            $works,
        );
    }
}
