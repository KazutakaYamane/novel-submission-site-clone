<?php

namespace App\Domain\Work;

use App\Domain\Episode\EpisodeSummary;

/**
 * ゲストに見せる作品。作者はメールアドレスを持たせず、名前だけにする
 */
final readonly class PublishedWork
{
    /**
     * @param  list<EpisodeSummary>  $episodes  公開済みの話だけを position の順に並べた目次
     */
    public function __construct(
        public Work $work,
        public string $authorName,
        public array $episodes,
    ) {}
}
