<?php

namespace App\Domain\Episode;

use DateTimeImmutable;

final readonly class Episode
{
    public function __construct(
        public string $id,
        public string $workId,
        public string $title,
        public string $body,
        public int $position,
        public EpisodeStatus $status,
        public ?DateTimeImmutable $publishedAt,
    ) {}
}
