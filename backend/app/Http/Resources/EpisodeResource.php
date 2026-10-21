<?php

namespace App\Http\Resources;

use App\Domain\Episode\Episode;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Episode $resource
 */
class EpisodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'position' => $this->resource->position,
            'status' => $this->resource->status,
            'published_at' => $this->resource->publishedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
