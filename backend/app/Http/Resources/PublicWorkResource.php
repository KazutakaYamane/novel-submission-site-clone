<?php

namespace App\Http\Resources;

use App\Domain\Work\PublishedWork;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PublishedWork $resource
 */
class PublicWorkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $work = $this->resource->work;

        // 認証なしで返すので、作者の ID・メールアドレスや作成日時など、執筆用の項目を含めない
        return [
            'id' => $work->id,
            'title' => $work->title,
            'catchphrase' => $work->catchphrase,
            'introduction' => $work->introduction,
            'genre' => $work->genre,
            'status' => $work->status,
            'rating_flags' => $work->ratingFlags,
            'tags' => $work->tags,
            'published_at' => $work->publishedAt?->format(DateTimeInterface::ATOM),
            'last_episode_published_at' => $work->lastEpisodePublishedAt?->format(DateTimeInterface::ATOM),
            'author' => [
                'name' => $this->resource->authorName,
            ],
            'episodes' => PublicEpisodeSummaryResource::collection($this->resource->episodes),
        ];
    }
}
