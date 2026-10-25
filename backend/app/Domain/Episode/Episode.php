<?php

namespace App\Domain\Episode;

use DateTimeImmutable;

/**
 * @phpstan-import-type EpisodeChanges from EpisodeRepository
 */
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

    /**
     * @throws InvalidEpisodeTransition
     * @throws EmptyEpisodeBody
     */
    public function publish(DateTimeImmutable $at): self
    {
        if ($this->status !== EpisodeStatus::Draft) {
            throw new InvalidEpisodeTransition;
        }
        if (self::isBlank($this->body)) {
            throw new EmptyEpisodeBody;
        }

        return clone ($this, ['status' => EpisodeStatus::Published, 'publishedAt' => $at]);
    }

    /**
     * @throws InvalidEpisodeTransition
     */
    public function unpublish(): self
    {
        if ($this->status !== EpisodeStatus::Published) {
            throw new InvalidEpisodeTransition;
        }

        return clone ($this, ['status' => EpisodeStatus::Draft, 'publishedAt' => null]);
    }

    /**
     * 公開済みの話は、本文を空にして読めない状態にさせない
     *
     * @throws EmptyEpisodeBody
     */
    public function assertBodyAcceptable(string $body): void
    {
        if ($this->status === EpisodeStatus::Published && self::isBlank($body)) {
            throw new EmptyEpisodeBody;
        }
    }

    /**
     * @param  EpisodeChanges  $changes
     */
    public function withChanges(array $changes): self
    {
        return clone ($this, $changes);
    }

    private static function isBlank(string $body): bool
    {
        // trim() は全角空白を除かないので、Unicode の空白(\p{Z})も空白として扱う
        return preg_match('/\A[\s\p{Z}]*\z/u', $body) === 1;
    }
}
