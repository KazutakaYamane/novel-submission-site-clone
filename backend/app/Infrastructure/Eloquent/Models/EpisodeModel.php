<?php

namespace App\Infrastructure\Eloquent\Models;

use App\Domain\Episode\EpisodeStatus;
use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['work_id', 'title', 'body', 'position', 'status'])]
#[UseFactory(EpisodeFactory::class)]
#[Table('episodes')]
class EpisodeModel extends Model
{
    /** @use HasFactory<EpisodeFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * 公開 API が返してよい話だけに絞る
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', EpisodeStatus::Published->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
