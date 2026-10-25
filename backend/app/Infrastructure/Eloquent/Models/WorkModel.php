<?php

namespace App\Infrastructure\Eloquent\Models;

use Database\Factories\WorkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'title', 'catchphrase', 'introduction', 'genre', 'status', 'rating_flags'])]
#[UseFactory(WorkFactory::class)]
#[Table('works')]
class WorkModel extends Model
{
    /** @use HasFactory<WorkFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsToMany<TagModel, $this>
     */
    public function tags(): BelongsToMany
    {
        // 中間テーブルの列名はクラス名から推測させず明示する(WorkModel からは work_model_id と推測される)。
        // 中間テーブルに順序を持たせないので、小文字にした名前のコードポイント順で返す。
        // name の照合順序で並べると、大文字小文字以外が違うだけの名前(「ハハ」と「パパ」)の順が決まらない
        return $this->belongsToMany(TagModel::class, 'work_tags', 'work_id', 'tag_id')->orderBy('normalized_name');
    }

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
