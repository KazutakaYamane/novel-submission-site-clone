<?php

namespace App\Http\Resources;

use App\Domain\Work\Work;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Work $resource
 */
class WorkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // enum は値に変換せずに返す。JSON では値になり、OpenAPI では値の一覧を持つ型になる
        // (要素の上のコメントは Scramble が description に出すので、ここに書く)
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'catchphrase' => $this->resource->catchphrase,
            'introduction' => $this->resource->introduction,
            'genre' => $this->resource->genre,
            'status' => $this->resource->status,
            'rating_flags' => $this->resource->ratingFlags,
            'tags' => $this->resource->tags,
            'published_at' => $this->resource->publishedAt?->format(DateTimeInterface::ATOM),
            'last_episode_published_at' => $this->resource->lastEpisodePublishedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
