<?php

namespace App\Domain\Work;

/**
 * 公開作品の一覧の 1 件。目次の代わりに公開済みの話の数を持つ
 */
final readonly class PublishedWorkSummary
{
    public function __construct(
        public Work $work,
        public string $authorName,
        public int $publishedEpisodeCount,
    ) {}
}
