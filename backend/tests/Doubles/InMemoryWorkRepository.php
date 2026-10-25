<?php

namespace Tests\Doubles;

use App\Domain\Work\Genre;
use App\Domain\Work\Work;
use App\Domain\Work\WorkRepository;
use App\Domain\Work\WorkStatus;
use DateTimeImmutable;

final class InMemoryWorkRepository implements WorkRepository
{
    /** @var array<string, Work> */
    public array $rows = [];

    /** @var array<string, string> 小文字にした名前 => 最初に登録された表記。本物の tags テーブルと同じく作品の間で共有する */
    private array $tagNames = [];

    /** @var list<string> findByIdForUpdate でロックした作品の id */
    public array $lockedIds = [];

    private int $sequence = 0;

    public function create(
        string $userId,
        string $title,
        ?string $catchphrase,
        string $introduction,
        Genre $genre,
        WorkStatus $status,
        array $ratingFlags,
        array $tags,
    ): Work {
        $work = new Work(
            // addWork で入れた行の id と重ならないよう、接頭辞を分ける
            id: 'created-'.++$this->sequence,
            userId: $userId,
            title: $title,
            catchphrase: $catchphrase,
            introduction: $introduction,
            genre: $genre,
            status: $status,
            ratingFlags: $ratingFlags,
            tags: $this->storeTags($tags),
            publishedAt: null,
            lastEpisodePublishedAt: null,
            createdAt: new DateTimeImmutable,
        );
        $this->rows[$work->id] = $work;

        return $work;
    }

    public function findById(string $id): ?Work
    {
        return $this->rows[$id] ?? null;
    }

    public function findByIdForUpdate(string $id): ?Work
    {
        $work = $this->findById($id);
        if ($work !== null) {
            $this->lockedIds[] = $id;
        }

        return $work;
    }

    public function listByUser(string $userId): array
    {
        $works = array_values(array_filter($this->rows, fn (Work $work) => $work->userId === $userId));
        // 本物は同じ時刻なら UUIDv7 の id の降順。ここでは連番の id で代える
        usort($works, fn (Work $a, Work $b) => [$b->createdAt, $b->id] <=> [$a->createdAt, $a->id]);

        return $works;
    }

    public function update(string $id, array $changes): ?Work
    {
        $stored = $this->rows[$id] ?? null;
        if ($stored === null) {
            return null;
        }

        return $this->rows[$id] = new Work(
            id: $stored->id,
            userId: $stored->userId,
            title: $changes['title'] ?? $stored->title,
            catchphrase: array_key_exists('catchphrase', $changes) ? $changes['catchphrase'] : $stored->catchphrase,
            introduction: $changes['introduction'] ?? $stored->introduction,
            genre: $changes['genre'] ?? $stored->genre,
            status: $changes['status'] ?? $stored->status,
            ratingFlags: $changes['ratingFlags'] ?? $stored->ratingFlags,
            tags: array_key_exists('tags', $changes) ? $this->storeTags($changes['tags']) : $stored->tags,
            publishedAt: $stored->publishedAt,
            lastEpisodePublishedAt: $stored->lastEpisodePublishedAt,
            createdAt: $stored->createdAt,
        );
    }

    public function savePublication(Work $work): void
    {
        $stored = $this->rows[$work->id] ?? null;
        if ($stored === null) {
            return;
        }

        $this->rows[$work->id] = new Work(
            id: $stored->id,
            userId: $stored->userId,
            title: $stored->title,
            catchphrase: $stored->catchphrase,
            introduction: $stored->introduction,
            genre: $stored->genre,
            status: $stored->status,
            ratingFlags: $stored->ratingFlags,
            tags: $stored->tags,
            publishedAt: $work->publishedAt,
            lastEpisodePublishedAt: $work->lastEpisodePublishedAt,
            createdAt: $stored->createdAt,
        );
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function storeTags(array $names): array
    {
        $tags = [];
        foreach ($names as $name) {
            $key = mb_strtolower($name);
            $this->tagNames[$key] ??= $name;
            $tags[$key] = $this->tagNames[$key];
        }
        // 本物は utf8mb4_bin の normalized_name で並べる。バイト順は UTF-8 のコードポイント順と同じ
        ksort($tags, SORT_STRING);

        return array_values($tags);
    }
}
