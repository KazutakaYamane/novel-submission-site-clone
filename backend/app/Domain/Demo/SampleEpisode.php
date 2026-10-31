<?php

namespace App\Domain\Demo;

use DateInterval;

final readonly class SampleEpisode
{
    /**
     * @param  DateInterval|null  $publishedBefore  デモを始めた時刻からどれだけ前に公開したことにするか。null なら下書き
     */
    public function __construct(
        public string $title,
        public string $body,
        public ?DateInterval $publishedBefore,
    ) {}
}
