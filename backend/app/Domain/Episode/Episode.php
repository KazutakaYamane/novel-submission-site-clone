<?php

namespace App\Domain\Episode;

use DateTimeImmutable;

/**
 * @phpstan-import-type EpisodeChanges from EpisodeRepositoryInterface
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
     * @throws InvalidEpisodeTransitionException
     * @throws EmptyEpisodeBodyException
     */
    public function publish(DateTimeImmutable $at): self
    {
        if ($this->status !== EpisodeStatus::Draft) {
            throw new InvalidEpisodeTransitionException;
        }
        if (self::isBlank($this->body)) {
            throw new EmptyEpisodeBodyException;
        }

        return clone ($this, ['status' => EpisodeStatus::Published, 'publishedAt' => $at]);
    }

    /**
     * @throws InvalidEpisodeTransitionException
     */
    public function unpublish(): self
    {
        if ($this->status !== EpisodeStatus::Published) {
            throw new InvalidEpisodeTransitionException;
        }

        return clone ($this, ['status' => EpisodeStatus::Draft, 'publishedAt' => null]);
    }

    /**
     * 公開済みの話は、本文を空にして読めない状態にさせない
     *
     * @throws EmptyEpisodeBodyException
     */
    public function assertBodyAcceptable(string $body): void
    {
        if ($this->status === EpisodeStatus::Published && self::isBlank($body)) {
            throw new EmptyEpisodeBodyException;
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
