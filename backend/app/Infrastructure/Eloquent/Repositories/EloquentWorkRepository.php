<?php

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\Work;
use App\Domain\Work\WorkRepository;
use App\Domain\Work\WorkStatus;
use App\Infrastructure\Eloquent\Models\Work as WorkModel;

final class EloquentWorkRepository implements WorkRepository
{
    public function create(
        string $userId,
        string $title,
        ?string $catchphrase,
        string $introduction,
        Genre $genre,
        WorkStatus $status,
        array $ratingFlags,
    ): Work {
        $model = WorkModel::create([
            'user_id' => $userId,
            'title' => $title,
            'catchphrase' => $catchphrase,
            'introduction' => $introduction,
            'genre' => $genre->value,
            'status' => $status->value,
            'rating_flags' => self::ratingFlagValues($ratingFlags),
        ]);

        return self::toEntity($model);
    }

    public function findById(string $id): ?Work
    {
        $model = WorkModel::find($id);

        return $model === null ? null : self::toEntity($model);
    }

    public function listByUser(string $userId): array
    {
        return WorkModel::where('user_id', $userId)
            ->latest()
            // created_at は秒単位なので、同じ秒の作品は UUIDv7 の id で作った順に並べる
            ->orderByDesc('id')
            ->get()
            ->map(fn (WorkModel $model) => self::toEntity($model))
            ->values()
            ->all();
    }

    public function update(string $id, array $changes): ?Work
    {
        $model = WorkModel::find($id);
        if ($model === null) {
            return null;
        }

        $attributes = [];
        if (array_key_exists('title', $changes)) {
            $attributes['title'] = $changes['title'];
        }
        if (array_key_exists('catchphrase', $changes)) {
            $attributes['catchphrase'] = $changes['catchphrase'];
        }
        if (array_key_exists('introduction', $changes)) {
            $attributes['introduction'] = $changes['introduction'];
        }
        if (array_key_exists('genre', $changes)) {
            $attributes['genre'] = $changes['genre']->value;
        }
        if (array_key_exists('status', $changes)) {
            $attributes['status'] = $changes['status']->value;
        }
        if (array_key_exists('ratingFlags', $changes)) {
            $attributes['rating_flags'] = self::ratingFlagValues($changes['ratingFlags']);
        }

        // save() は値が変わった列だけを UPDATE し、何も変わらなければ updated_at も変えない
        $model->fill($attributes)->save();

        return self::toEntity($model);
    }

    /**
     * @param  list<RatingFlag>  $flags
     * @return list<string>
     */
    private static function ratingFlagValues(array $flags): array
    {
        return array_map(fn (RatingFlag $flag) => $flag->value, $flags);
    }

    private static function toEntity(WorkModel $model): Work
    {
        return new Work(
            id: $model->id,
            userId: $model->user_id,
            title: $model->title,
            catchphrase: $model->catchphrase,
            introduction: $model->introduction,
            genre: Genre::from($model->genre),
            status: WorkStatus::from($model->status),
            ratingFlags: array_map(RatingFlag::from(...), $model->rating_flags),
            publishedAt: $model->published_at?->toDateTimeImmutable(),
            lastEpisodePublishedAt: $model->last_episode_published_at?->toDateTimeImmutable(),
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
