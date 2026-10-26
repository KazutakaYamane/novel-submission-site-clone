<?php

namespace App\UseCases\Episode;

use App\Domain\Episode\EmptyEpisodeBodyException;
use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeNotFoundException;
use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\Episode\InvalidEpisodeTransitionException;
use App\Domain\Shared\ClockInterface;
use App\Domain\Shared\PublicPageCacheInterface;
use App\Domain\Shared\TransactionInterface;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Domain\Work\WorkRepositoryInterface;

final readonly class PublishEpisodeUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
        private EpisodeRepositoryInterface $episodes,
        private TransactionInterface $transaction,
        private ClockInterface $clock,
        private PublicPageCacheInterface $pageCache,
    ) {}

    /**
     * @throws WorkNotFoundException
     * @throws WorkAccessDeniedException
     * @throws EpisodeNotFoundException
     * @throws InvalidEpisodeTransitionException
     * @throws EmptyEpisodeBodyException
     */
    public function __invoke(string $userId, string $workId, string $episodeId): Episode
    {
        // 話より先に作品の権限を確かめ、他人の作品に下書きがあるかどうかを漏らさない。
        // ロックより前に確かめ、他人が作品の行をロックして作者の操作を待たせられないようにする
        $work = $this->works->findById($workId) ?? throw new WorkNotFoundException;
        $work->assertEditableBy($userId);

        $published = $this->transaction->run(function () use ($workId, $episodeId) {
            // 同じ作品の公開・非公開化を作品の行で直列にし、二重公開と last_episode_published_at の書き戻し合いを防ぐ
            $work = $this->works->findByIdForUpdate($workId) ?? throw new WorkNotFoundException;
            $episode = $this->episodes->findInWorkForUpdate($workId, $episodeId) ?? throw new EpisodeNotFoundException;

            $now = $this->clock->now();
            $published = $episode->publish($now);
            $this->episodes->savePublication($published);
            $this->works->savePublication($work->recordEpisodePublished($now));

            return $published;
        });

        $this->pageCache->forgetEpisode($workId, $episodeId);

        return $published;
    }
}
