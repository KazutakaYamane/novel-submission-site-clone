<?php

namespace App\Domain\Work;

use DateTimeImmutable;

final readonly class Work
{
    /**
     * @param  list<RatingFlag>  $ratingFlags
     */
    public function __construct(
        public string $id,
        public string $userId,
        public string $title,
        public ?string $catchphrase,
        public string $introduction,
        public Genre $genre,
        public WorkStatus $status,
        public array $ratingFlags,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $lastEpisodePublishedAt,
        public DateTimeImmutable $createdAt,
    ) {}

    /**
     * @throws WorkNotFound
     * @throws WorkAccessDenied
     */
    public function assertEditableBy(string $userId): void
    {
        if ($this->userId === $userId) {
            return;
        }

        // 未公開の作品は、他人に存在を知られないよう存在しないときと同じに扱う
        throw $this->publishedAt === null ? new WorkNotFound : new WorkAccessDenied;
    }
}
