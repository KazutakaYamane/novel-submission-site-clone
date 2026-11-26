<?php

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Episode\EpisodeStatus;
use App\Domain\Work\Genre;
use App\Domain\Work\MyWorkSummary;
use App\Domain\Work\MyWorkSummaryRepositoryInterface;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\Work;
use App\Domain\Work\WorkRepositoryInterface;
use App\Domain\Work\WorkStatus;
use App\Infrastructure\Eloquent\Models\TagModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type WorkChanges from WorkRepositoryInterface
 */
final class EloquentWorkRepository implements MyWorkSummaryRepositoryInterface, WorkRepositoryInterface
{
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
        $model = DB::transaction(function () use ($userId, $title, $catchphrase, $introduction, $genre, $status, $ratingFlags, $tags) {
            $model = WorkModel::create([
                'user_id' => $userId,
                'title' => $title,
                'catchphrase' => $catchphrase,
                'introduction' => $introduction,
                'genre' => $genre->value,
                'status' => $status->value,
                'rating_flags' => self::ratingFlagValues($ratingFlags),
            ]);
            self::syncTags($model, $tags);

            return $model;
        });

        return self::toEntity($model->load('tags'));
    }

    public function findById(string $id): ?Work
    {
        $model = WorkModel::with('tags')->find($id);

        return $model === null ? null : self::toEntity($model);
    }

    public function findPublished(string $id): ?Work
    {
        $model = WorkModel::with('tags')->published()->find($id);

        return $model === null ? null : self::toEntity($model);
    }

    public function findByIdForUpdate(string $id): ?Work
    {
        $model = WorkModel::with('tags')->lockForUpdate()->find($id);

        return $model === null ? null : self::toEntity($model);
    }

    public function listSummariesByUser(string $userId): array
    {
        // works.updated_at は作品の行を書き換えたときだけ変わり、話の保存では変わらない。
        // 話の保存のたびに作品の行を書き換えず、話の数と更新日時は読むときに episodes から集計する
        return WorkModel::with('tags')
            ->withCount([
                'episodes as published_episode_count' => fn (Builder $query) => $query->where('status', EpisodeStatus::Published->value),
                'episodes as draft_episode_count' => fn (Builder $query) => $query->where('status', EpisodeStatus::Draft->value),
            ])
            ->withMax('episodes', 'updated_at')
            ->withCasts(['episodes_max_updated_at' => 'datetime'])
            ->where('user_id', $userId)
            ->orderByRaw('GREATEST(updated_at, COALESCE(episodes_max_updated_at, updated_at)) DESC')
            // updated_at は秒単位なので、同じ秒の作品は UUIDv7 の id で作った順に並べる
            ->orderByDesc('id')
            ->get()
            ->map(fn (WorkModel $model) => self::toSummary($model))
            ->values()
            ->all();
    }

    public function listByLastEpisodePublishedAt(int $limit): array
    {
        // 全話を非公開に戻した作品は published_at が残るので、last_episode_published_at で絞る
        return WorkModel::with('tags')
            ->whereNotNull('last_episode_published_at')
            ->orderByDesc('last_episode_published_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (WorkModel $model) => self::toEntity($model))
            ->values()
            ->all();
    }

    public function update(string $id, array $changes): ?Work
    {
        return DB::transaction(function () use ($id, $changes) {
            // 同じ作品への同時の更新が、中間テーブルの同じ行を同時に INSERT しないようにする
            $model = WorkModel::lockForUpdate()->find($id);
            if ($model === null) {
                return null;
            }

            $model->fill(self::attributes($changes));
            $tagsChanged = array_key_exists('tags', $changes) && self::syncTags($model, $changes['tags']);
            // save() は値が変わった列だけを UPDATE し、何も変わらなければ updated_at も変えない。
            // タグは works の列ではないので、変わったときは touch() で updated_at を変える
            $tagsChanged ? $model->touch() : $model->save();

            return self::toEntity($model->load('tags'));
        });
    }

    public function savePublication(Work $work): void
    {
        WorkModel::whereKey($work->id)->update([
            'published_at' => $work->publishedAt,
            'last_episode_published_at' => $work->lastEpisodePublishedAt,
        ]);
    }

    /**
     * @param  WorkChanges  $changes
     * @return array<string, mixed>
     */
    private static function attributes(array $changes): array
    {
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

        return $attributes;
    }

    /**
     * @param  list<string>  $names
     * @return bool 作品のタグが変わったか
     */
    private static function syncTags(WorkModel $model, array $names): bool
    {
        $rows = [];
        foreach ($names as $name) {
            $normalized = mb_strtolower($name);
            // 先に来た表記を残すので、同じキーを上書きしない
            $rows[$normalized] ??= ['name' => $name, 'normalized_name' => $normalized];
        }
        // upsert は既存の行を入力の順にロックするので、同時のリクエスト同士がデッドロックしないよう順を揃える
        ksort($rows, SORT_STRING);
        // 数字だけの名前は配列のキーにすると int になり、MySQL が数値として比べるので、検索には行の値を使う
        $normalizedNames = array_column($rows, 'normalized_name');

        // 既にあるタグは表記を変えずに使う。同時に同じタグを作るリクエストがあっても unique 違反にならない
        TagModel::upsert(array_values($rows), ['normalized_name'], ['normalized_name']);

        $result = $model->tags()->sync(TagModel::whereIn('normalized_name', $normalizedNames)->pluck('id')->all());

        return $result['attached'] !== [] || $result['detached'] !== [];
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
            tags: $model->tags->pluck('name')->values()->all(),
            publishedAt: $model->published_at?->toDateTimeImmutable(),
            lastEpisodePublishedAt: $model->last_episode_published_at?->toDateTimeImmutable(),
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }

    /**
     * listSummariesByUser の withCount・withMax で集計した列を読む
     */
    private static function toSummary(WorkModel $model): MyWorkSummary
    {
        return new MyWorkSummary(
            work: self::toEntity($model),
            publishedEpisodeCount: (int) $model->getAttribute('published_episode_count'),
            draftEpisodeCount: (int) $model->getAttribute('draft_episode_count'),
            updatedAt: max($model->updated_at, $model->getAttribute('episodes_max_updated_at') ?? $model->updated_at)->toDateTimeImmutable(),
        );
    }
}
