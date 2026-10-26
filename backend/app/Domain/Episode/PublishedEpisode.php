<?php

namespace App\Domain\Episode;

use App\Domain\Work\Work;

/**
 * ゲストに見せる話。作者はメールアドレスを持たせず、名前だけにする
 */
final readonly class PublishedEpisode
{
    public function __construct(
        public Episode $episode,
        public Work $work,
        public string $authorName,
        public ?EpisodeSummary $previous,
        public ?EpisodeSummary $next,
    ) {}
}
