<?php

namespace App\Http\Resources;

use App\Domain\Work\MyWorkSummary;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 作者の作品の一覧の 1 件。WorkResource の項目に、一覧を読むときに集計した話の数と更新日時を足す
 *
 * @property MyWorkSummary $resource
 */
class MyWorkSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new WorkResource($this->resource->work))->toArray($request),
            'published_episode_count' => $this->resource->publishedEpisodeCount,
            'draft_episode_count' => $this->resource->draftEpisodeCount,
            // 作品の更新日時と、その作品の話の更新日時のうち最も新しいもの
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
