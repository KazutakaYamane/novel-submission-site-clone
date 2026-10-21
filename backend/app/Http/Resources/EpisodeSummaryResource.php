<?php

namespace App\Http\Resources;

use App\Domain\Episode\EpisodeSummary;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property EpisodeSummary $resource
 */
class EpisodeSummaryResource extends JsonResource
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
            'status' => $this->resource->status,
            'published_at' => $this->resource->publishedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
