<?php

namespace App\Http\Resources;

use App\Domain\Episode\PublishedEpisode;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PublishedEpisode $resource
 */
class PublicEpisodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $episode = $this->resource->episode;
        $previous = $this->resource->previous;
        $next = $this->resource->next;

        return [
            'id' => $episode->id,
            'title' => $episode->title,
            'body' => $episode->body,
            'position' => $episode->position,
            'published_at' => $episode->publishedAt?->format(DateTimeInterface::ATOM),
            'work' => [
                'id' => $this->resource->work->id,
                'title' => $this->resource->work->title,
                'author' => [
                    'name' => $this->resource->authorName,
                ],
            ],
            'previous' => $previous === null ? null : ['id' => $previous->id, 'title' => $previous->title],
            'next' => $next === null ? null : ['id' => $next->id, 'title' => $next->title],
        ];
    }
}
