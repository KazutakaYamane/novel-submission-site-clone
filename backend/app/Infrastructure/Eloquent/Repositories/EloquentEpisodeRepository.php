<?php

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeRepository;
use App\Domain\Episode\EpisodeStatus;
use App\Domain\Episode\EpisodeSummary;
use App\Infrastructure\Eloquent\Models\Episode as EpisodeModel;
use App\Infrastructure\Eloquent\Models\Work as WorkModel;
use Illuminate\Support\Facades\DB;

final class EloquentEpisodeRepository implements EpisodeRepository
{
    public function appendDraft(string $workId, string $title, string $body): ?Episode
    {
        $model = DB::transaction(function () use ($workId, $title, $body) {
            // 同じ作品への同時の作成が同じ max(position) を読まないよう、作品の行で直列にする
            if (WorkModel::whereKey($workId)->lockForUpdate()->value('id') === null) {
                return null;
            }

            return EpisodeModel::create([
                'work_id' => $workId,
                'title' => $title,
                'body' => $body,
                'position' => (int) EpisodeModel::where('work_id', $workId)->max('position') + 1,
                'status' => EpisodeStatus::Draft->value,
            ]);
        });

        return $model === null ? null : self::toEntity($model);
    }

    public function findInWork(string $workId, string $episodeId): ?Episode
    {
        $model = EpisodeModel::where('work_id', $workId)->find($episodeId);

        return $model === null ? null : self::toEntity($model);
    }

    public function listByWork(string $workId): array
    {
        return EpisodeModel::where('work_id', $workId)
            ->orderBy('position')
            ->get(['id', 'title', 'position', 'status', 'published_at'])
            ->map(fn (EpisodeModel $model) => new EpisodeSummary(
                id: $model->id,
                title: $model->title,
                position: $model->position,
                status: EpisodeStatus::from($model->status),
                publishedAt: $model->published_at?->toDateTimeImmutable(),
            ))
            ->values()
            ->all();
    }

    public function update(string $workId, string $episodeId, array $changes): ?Episode
    {
        $model = EpisodeModel::where('work_id', $workId)->find($episodeId);
        if ($model === null) {
            return null;
        }

        // save() は値が変わった列だけを UPDATE するので、同時に別の項目を変えたリクエストの値を上書きしない
        $model->fill($changes)->save();

        return self::toEntity($model);
    }

    private static function toEntity(EpisodeModel $model): Episode
    {
        return new Episode(
            id: $model->id,
            workId: $model->work_id,
            title: $model->title,
            body: $model->body,
            position: $model->position,
            status: EpisodeStatus::from($model->status),
            publishedAt: $model->published_at?->toDateTimeImmutable(),
        );
    }
}
