<?php

namespace App\UseCases\Episode;

use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeNotFound;
use App\Domain\Episode\EpisodeRepository;
use App\Domain\Episode\InvalidEpisodeTransition;
use App\Domain\Shared\Transaction;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
use App\Domain\Work\WorkRepository;

final readonly class UnpublishEpisodeUseCase
{
    public function __construct(
        private WorkRepository $works,
        private EpisodeRepository $episodes,
        private Transaction $transaction,
    ) {}

    /**
     * @throws WorkNotFound
     * @throws WorkAccessDenied
     * @throws EpisodeNotFound
     * @throws InvalidEpisodeTransition
     */
    public function __invoke(string $userId, string $workId, string $episodeId): Episode
    {
        // ロックの前に確かめ、他人が作品の行をロックして作者の操作を待たせられないようにする
        $work = $this->works->findById($workId) ?? throw new WorkNotFound;
        $work->assertEditableBy($userId);

        return $this->transaction->run(function () use ($workId, $episodeId) {
            // 同じ作品の公開と同時に走っても、公開された話を含めて最大値を求めるよう作品の行で直列にする
            $work = $this->works->findByIdForUpdate($workId) ?? throw new WorkNotFound;
            $episode = $this->episodes->findInWorkForUpdate($workId, $episodeId) ?? throw new EpisodeNotFound;

            $unpublished = $episode->unpublish();
            $this->episodes->savePublication($unpublished);
            // 作品の published_at は戻さない。作品を非公開に戻す操作は作らない
            $this->works->savePublication($work->withLastEpisodePublishedAt($this->episodes->latestPublishedAt($workId)));

            return $unpublished;
        });
    }
}
