<?php

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Domain\Episode\EpisodeStatus;
use App\Domain\Episode\EpisodeSummary;
use App\Infrastructure\Eloquent\Models\EpisodeModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentEpisodeRepository implements EpisodeRepositoryInterface
{
    // 目次では本文を読み込まない
    private const array SUMMARY_COLUMNS = ['id', 'title', 'position', 'status', 'published_at'];

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

    public function findInWorkForUpdate(string $workId, string $episodeId): ?Episode
    {
        $model = EpisodeModel::where('work_id', $workId)->lockForUpdate()->find($episodeId);

        return $model === null ? null : self::toEntity($model);
    }

    public function listByWork(string $workId): array
    {
        return EpisodeModel::where('work_id', $workId)
            ->orderBy('position')
            ->get(self::SUMMARY_COLUMNS)
            ->map(fn (EpisodeModel $model) => self::toSummary($model))
            ->values()
            ->all();
    }

    public function listPublishedInWork(string $workId): array
    {
        return EpisodeModel::published()
            ->where('work_id', $workId)
            ->orderBy('position')
            ->get(self::SUMMARY_COLUMNS)
            ->map(fn (EpisodeModel $model) => self::toSummary($model))
            ->values()
            ->all();
    }

    public function findPublishedInWork(string $workId, string $episodeId): ?Episode
    {
        $model = EpisodeModel::published()->where('work_id', $workId)->find($episodeId);

        return $model === null ? null : self::toEntity($model);
    }

    public function findPublishedNeighbors(string $workId, int $position): array
    {
        $previous = EpisodeModel::published()
            ->where('work_id', $workId)
            ->where('position', '<', $position)
            ->orderByDesc('position')
            ->first(self::SUMMARY_COLUMNS);
        $next = EpisodeModel::published()
            ->where('work_id', $workId)
            ->where('position', '>', $position)
            ->orderBy('position')
            ->first(self::SUMMARY_COLUMNS);

        return [
            'previous' => $previous === null ? null : self::toSummary($previous),
            'next' => $next === null ? null : self::toSummary($next),
        ];
    }

    public function update(Episode $episode, array $changes): Episode
    {
        // 渡した列だけを UPDATE し、同時に別の項目を変えたリクエストの値を上書きしない
        EpisodeModel::whereKey($episode->id)->update($changes);

        return $episode->withChanges($changes);
    }

    public function savePublication(Episode $episode): void
    {
        EpisodeModel::whereKey($episode->id)->update([
            'status' => $episode->status->value,
            'published_at' => $episode->publishedAt,
        ]);
    }

    public function latestPublishedAt(string $workId): ?DateTimeImmutable
    {
        return EpisodeModel::published()
            ->where('work_id', $workId)
            ->latest('published_at')
            ->first(['published_at'])
            ?->published_at
            ?->toDateTimeImmutable();
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

    private static function toSummary(EpisodeModel $model): EpisodeSummary
    {
        return new EpisodeSummary(
            id: $model->id,
            title: $model->title,
            position: $model->position,
            status: EpisodeStatus::from($model->status),
            publishedAt: $model->published_at?->toDateTimeImmutable(),
        );
    }
}
