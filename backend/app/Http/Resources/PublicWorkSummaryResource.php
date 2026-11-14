<?php

namespace App\Http\Resources;

use App\Domain\Work\PublishedWorkSummary;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 公開作品の一覧の 1 件。一覧のカードに出す項目だけを返し、あらすじ・タグ・目次は作品ページで返す
 *
 * @property PublishedWorkSummary $resource
 */
class PublicWorkSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $work = $this->resource->work;

        return [
            'id' => $work->id,
            'title' => $work->title,
            'catchphrase' => $work->catchphrase,
            'genre' => $work->genre,
            'status' => $work->status,
            'published_episode_count' => $this->resource->publishedEpisodeCount,
            'last_episode_published_at' => $work->lastEpisodePublishedAt?->format(DateTimeInterface::ATOM),
            'author' => [
                'name' => $this->resource->authorName,
            ],
        ];
    }
}
