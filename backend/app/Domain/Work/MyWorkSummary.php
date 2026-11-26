<?php

namespace App\Domain\Work;

use DateTimeImmutable;

/**
 * 作者の作品の一覧の 1 件。話の数と更新日時は作品の不変条件ではないので Work に持たせず、一覧を読むときに集計する
 */
final readonly class MyWorkSummary
{
    /**
     * @param  DateTimeImmutable  $updatedAt  作品の更新日時と、その作品の話の更新日時のうち最も新しいもの
     */
    public function __construct(
        public Work $work,
        public int $publishedEpisodeCount,
        public int $draftEpisodeCount,
        public DateTimeImmutable $updatedAt,
    ) {}
}
