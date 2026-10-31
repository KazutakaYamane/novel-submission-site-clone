<?php

namespace Tests\Doubles;

use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\Episode\EpisodeStatus;
use App\Domain\Episode\EpisodeSummary;
use App\Domain\Work\WorkRepositoryInterface;
use DateTimeImmutable;

final class InMemoryEpisodeRepository implements EpisodeRepositoryInterface, RollbackableDouble
{
    /** @var array<string, Episode> */
    public array $rows = [];

    private int $sequence = 0;

    // 本物の episodes.work_id の外部キーと同じく、無い作品には話を足せない
    public function __construct(private WorkRepositoryInterface $works) {}

    public function appendDraft(string $workId, string $title, string $body): ?Episode
    {
        if ($this->works->findById($workId) === null) {
            return null;
        }

        $positions = array_map(
            fn (Episode $episode) => $episode->position,
            array_filter($this->rows, fn (Episode $episode) => $episode->workId === $workId),
        );
        $episode = new Episode(
            id: 'episode-'.++$this->sequence,
            workId: $workId,
            title: $title,
            body: $body,
            position: ($positions === [] ? 0 : max($positions)) + 1,
            status: EpisodeStatus::Draft,
            publishedAt: null,
        );

        return $this->rows[$episode->id] = $episode;
    }

    public function findInWork(string $workId, string $episodeId): ?Episode
    {
        $episode = $this->rows[$episodeId] ?? null;

        return $episode?->workId === $workId ? $episode : null;
    }

    public function findInWorkForUpdate(string $workId, string $episodeId): ?Episode
    {
        return $this->findInWork($workId, $episodeId);
    }

    public function listByWork(string $workId): array
    {
        $episodes = array_values(array_filter($this->rows, fn (Episode $episode) => $episode->workId === $workId));
        usort($episodes, fn (Episode $a, Episode $b) => $a->position <=> $b->position);

        return array_map(self::toSummary(...), $episodes);
    }

    public function listPublishedInWork(string $workId): array
    {
        return array_values(array_filter(
            $this->listByWork($workId),
            fn (EpisodeSummary $episode) => $episode->status === EpisodeStatus::Published,
        ));
    }

    public function findPublishedInWork(string $workId, string $episodeId): ?Episode
    {
        $episode = $this->findInWork($workId, $episodeId);

        return $episode?->status === EpisodeStatus::Published ? $episode : null;
    }

    public function findPublishedNeighbors(string $workId, int $position): array
    {
        $previous = null;
        $next = null;
        foreach ($this->listPublishedInWork($workId) as $episode) {
            if ($episode->position < $position) {
                $previous = $episode;
            }
            if ($episode->position > $position) {
                $next ??= $episode;
            }
        }

        return ['previous' => $previous, 'next' => $next];
    }

    public function update(Episode $episode, array $changes): Episode
    {
        // 本物の UPDATE と同じく、行が無ければ何も書かない
        $stored = $this->rows[$episode->id] ?? null;
        if ($stored === null) {
            return $episode->withChanges($changes);
        }

        return $this->rows[$episode->id] = new Episode(
            id: $stored->id,
            workId: $stored->workId,
            title: $changes['title'] ?? $stored->title,
            body: $changes['body'] ?? $stored->body,
            position: $stored->position,
            status: $stored->status,
            publishedAt: $stored->publishedAt,
        );
    }

    public function savePublication(Episode $episode): void
    {
        $stored = $this->rows[$episode->id] ?? null;
        if ($stored === null) {
            return;
        }

        $this->rows[$episode->id] = new Episode(
            id: $stored->id,
            workId: $stored->workId,
            title: $stored->title,
            body: $stored->body,
            position: $stored->position,
            status: $episode->status,
            publishedAt: $episode->publishedAt,
        );
    }

    public function latestPublishedAt(string $workId): ?DateTimeImmutable
    {
        $dates = array_map(
            fn (Episode $episode) => $episode->publishedAt,
            array_filter(
                $this->rows,
                fn (Episode $episode) => $episode->workId === $workId && $episode->status === EpisodeStatus::Published,
            ),
        );

        return $dates === [] ? null : max($dates);
    }

    /**
     * @return array<string, Episode>
     */
    public function snapshot(): array
    {
        return $this->rows;
    }

    public function restore(mixed $snapshot): void
    {
        $this->rows = $snapshot;
    }

    private static function toSummary(Episode $episode): EpisodeSummary
    {
        return new EpisodeSummary(
            id: $episode->id,
            title: $episode->title,
            position: $episode->position,
            status: $episode->status,
            publishedAt: $episode->publishedAt,
        );
    }
}
