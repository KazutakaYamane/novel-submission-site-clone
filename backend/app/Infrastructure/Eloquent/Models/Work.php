<?php

namespace App\Infrastructure\Eloquent\Models;

use Database\Factories\WorkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'title', 'catchphrase', 'introduction', 'genre', 'status', 'rating_flags'])]
#[UseFactory(WorkFactory::class)]
class Work extends Model
{
    /** @use HasFactory<WorkFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating_flags' => 'array',
            'published_at' => 'datetime',
            'last_episode_published_at' => 'datetime',
        ];
    }
}
