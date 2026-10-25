<?php

namespace App\Http\Resources;

use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeStatus;
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
            // Laravel はフロントのオリジンを知らないので、URL ではなく Next.js 上のパスを返す
            'public_path' => $this->resource->status === EpisodeStatus::Published
                ? "/works/{$this->resource->workId}/episodes/{$this->resource->id}"
                : null,
        ];
    }
}
