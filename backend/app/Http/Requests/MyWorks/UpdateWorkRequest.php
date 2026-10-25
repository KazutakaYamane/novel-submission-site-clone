<?php

namespace App\Http\Requests\MyWorks;

use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\WorkRepositoryInterface;
use App\Domain\Work\WorkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

/**
 * @phpstan-import-type WorkChanges from WorkRepositoryInterface
 */
class UpdateWorkRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // rating_flags・tags の空の配列は「全部外す」なので required にしない
        // (要素の上のコメントは Scramble が description に出すので、ここに書く)
        return [
            'title' => ['sometimes', 'required', 'string', 'max:'.Config::integer('work.max_length.title')],
            'catchphrase' => ['sometimes', 'nullable', 'string', 'max:'.Config::integer('work.max_length.catchphrase')],
            'introduction' => ['sometimes', 'required', 'string', 'max:'.Config::integer('work.max_length.introduction')],
            'genre' => ['sometimes', 'required', Rule::enum(Genre::class)],
            'status' => ['sometimes', 'required', Rule::enum(WorkStatus::class)],
            'rating_flags' => ['sometimes', 'list'],
            'rating_flags.*' => [Rule::enum(RatingFlag::class), 'distinct'],
            'tags' => ['sometimes', 'list', 'max:'.Config::integer('work.tags.max_count')],
            'tags.*' => ['required', 'string', 'max:'.Config::integer('work.tags.max_length')],
        ];
    }

    /**
     * 送られた項目だけを返す
     *
     * @return WorkChanges
     */
    public function changes(): array
    {
        $validated = $this->validated();
        $changes = [];

        if (array_key_exists('title', $validated)) {
            $changes['title'] = $this->string('title')->toString();
        }
        if (array_key_exists('catchphrase', $validated)) {
            $changes['catchphrase'] = $validated['catchphrase'];
        }
        if (array_key_exists('introduction', $validated)) {
            $changes['introduction'] = $this->string('introduction')->toString();
        }
        if (array_key_exists('genre', $validated)) {
            $changes['genre'] = Genre::from($validated['genre']);
        }
        if (array_key_exists('status', $validated)) {
            $changes['status'] = WorkStatus::from($validated['status']);
        }
        if (array_key_exists('rating_flags', $validated)) {
            $changes['ratingFlags'] = array_map(RatingFlag::from(...), $validated['rating_flags']);
        }
        if (array_key_exists('tags', $validated)) {
            $changes['tags'] = $validated['tags'];
        }

        return $changes;
    }
}
