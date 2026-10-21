<?php

namespace App\Domain\Episode;

use DateTimeImmutable;

/**
 * 目次に並べる話。本文は大きいので一覧では読み込まない
 */
final readonly class EpisodeSummary
{
    public function __construct(
        public string $id,
        public string $title,
        public int $position,
        public EpisodeStatus $status,
        public ?DateTimeImmutable $publishedAt,
    ) {}
}
