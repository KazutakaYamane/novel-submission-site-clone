<?php

namespace App\UseCases\Episode;

use App\Domain\Episode\EmptyEpisodeBodyException;
use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeNotFoundException;
use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\Shared\TransactionInterface;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Domain\Work\WorkRepositoryInterface;

/**
 * @phpstan-import-type EpisodeChanges from EpisodeRepositoryInterface
 */
final readonly class UpdateMyEpisodeUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
        private EpisodeRepositoryInterface $episodes,
        private TransactionInterface $transaction,
    ) {}

    /**
     * @param  EpisodeChanges  $changes
     *
     * @throws WorkNotFoundException
     * @throws WorkAccessDeniedException
     * @throws EpisodeNotFoundException
     * @throws EmptyEpisodeBodyException
     */
    public function __invoke(string $userId, string $workId, string $episodeId, array $changes): Episode
    {
        // 話より先に作品の権限を確かめ、他人の作品に下書きがあるかどうかを漏らさない
        $work = $this->works->findById($workId) ?? throw new WorkNotFoundException;
        $work->assertEditableBy($userId);

        return $this->transaction->run(function () use ($workId, $episodeId, $changes) {
            // 状態を確かめてから書き込むまでに公開されないよう、話の行をロックして読む
            $episode = $this->episodes->findInWorkForUpdate($workId, $episodeId) ?? throw new EpisodeNotFoundException;
            if (array_key_exists('body', $changes)) {
                $episode->assertBodyAcceptable($changes['body']);
            }

            return $this->episodes->update($episode, $changes);
        });
    }
}
