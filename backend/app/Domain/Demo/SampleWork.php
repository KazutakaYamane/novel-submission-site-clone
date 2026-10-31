<?php

namespace App\Domain\Demo;

use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\WorkStatus;

/**
 * デモ作者に渡す初期作品の中身
 */
final readonly class SampleWork
{
    /**
     * @param  list<RatingFlag>  $ratingFlags
     * @param  list<string>  $tags
     * @param  list<SampleEpisode>  $episodes
     */
    public function __construct(
        public string $title,
        public ?string $catchphrase,
        public string $introduction,
        public Genre $genre,
        public WorkStatus $status,
        public array $ratingFlags,
        public array $tags,
        public array $episodes,
    ) {}
}
