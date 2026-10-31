<?php

namespace App\UseCases\Work;

use App\Domain\Episode\EmptyEpisodeBodyException;
use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\Episode\PublishAtInFutureException;
use App\Domain\Shared\ClockInterface;
use App\Domain\Shared\PublicPageCacheInterface;
use App\Domain\Shared\TransactionInterface;
use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\Work;
use App\Domain\Work\WorkRepositoryInterface;
use App\Domain\Work\WorkStatus;
use DateTimeImmutable;
use LogicException;

final readonly class ImportWorkUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
        private EpisodeRepositoryInterface $episodes,
        private TransactionInterface $transaction,
        private ClockInterface $clock,
        private PublicPageCacheInterface $pageCache,
    ) {}

    /**
     * @param  list<RatingFlag>  $ratingFlags
     * @param  list<string>  $tags
     * @param  list<array{title: string, body: string, publishedAt: DateTimeImmutable|null}>  $episodes  渡した順に position を振る。publishedAt が null の話は下書きにする
     *
     * @throws PublishAtInFutureException
     * @throws EmptyEpisodeBodyException
     */
    public function __invoke(
        string $userId,
        string $title,
        ?string $catchphrase,
        string $introduction,
        Genre $genre,
        WorkStatus $status,
        array $ratingFlags,
        array $tags,
        array $episodes,
    ): Work {
        $now = $this->clock->now();
        foreach ($episodes as $i => $episode) {
            if ($episode['publishedAt'] === null) {
                continue;
            }
            // 予約公開(scheduled)の状態はまだ無いので、未来の日時で公開済みにすると公開前の話が読めてしまう
            if ($episode['publishedAt'] > $now) {
                throw new PublishAtInFutureException;
            }
            // DB の列はタイムゾーンと秒未満を持たず、Eloquent は渡した値の表記をそのまま書く。
            // 時計が返す値と同じタイムゾーン・秒単位にそろえ、保存した値と返す値を一致させる
            $episodes[$i]['publishedAt'] = (new DateTimeImmutable('@'.$episode['publishedAt']->getTimestamp()))
                ->setTimezone($now->getTimezone());
        }

        $work = $this->transaction->run(function () use ($userId, $title, $catchphrase, $introduction, $genre, $status, $ratingFlags, $tags, $episodes) {
            $work = $this->works->create($userId, $title, $catchphrase, $introduction, $genre, $status, $ratingFlags, $tags);

            $publishedAts = [];
            foreach ($episodes as $input) {
                $episode = $this->episodes->appendDraft($work->id, $input['title'], $input['body'])
                    ?? throw new LogicException('同じトランザクションで作った作品が見つからない');
                if ($input['publishedAt'] !== null) {
                    $this->episodes->savePublication($episode->publish($input['publishedAt']));
                    $publishedAts[] = $input['publishedAt'];
                }
            }
            if ($publishedAts === []) {
                return $work;
            }

            // recordEpisodePublished は published_at を最初の 1 回だけ書くので、古い順に渡して最小値を残す
            sort($publishedAts);
            foreach ($publishedAts as $at) {
                $work = $work->recordEpisodePublished($at);
            }
            $this->works->savePublication($work);

            return $work;
        });

        if ($work->publishedAt !== null) {
            $this->pageCache->forgetWork($work->id);
        }

        return $work;
    }
}
