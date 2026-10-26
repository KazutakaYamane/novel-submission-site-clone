<?php

namespace App\Http\Resources;

use App\Domain\Episode\EpisodeSummary;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 公開済みの作品の目次の 1 話。公開済みの話だけを並べるので status は返さない
 *
 * @property EpisodeSummary $resource
 */
class PublicEpisodeSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'position' => $this->resource->position,
            'published_at' => $this->resource->publishedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
