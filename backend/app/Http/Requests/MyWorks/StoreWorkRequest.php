<?php

namespace App\Http\Requests\MyWorks;

use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\WorkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class StoreWorkRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.Config::integer('work.max_length.title')],
            'catchphrase' => ['nullable', 'string', 'max:'.Config::integer('work.max_length.catchphrase')],
            'introduction' => ['required', 'string', 'max:'.Config::integer('work.max_length.introduction')],
            'genre' => ['required', Rule::enum(Genre::class)],
            'status' => ['sometimes', Rule::enum(WorkStatus::class)],
            'rating_flags' => ['sometimes', 'list'],
            'rating_flags.*' => [Rule::enum(RatingFlag::class), 'distinct'],
            'tags' => ['sometimes', 'list', 'max:'.Config::integer('work.tags.max_count')],
            'tags.*' => ['required', 'string', 'max:'.Config::integer('work.tags.max_length')],
        ];
    }

    public function genre(): Genre
    {
        return Genre::from($this->validated('genre'));
    }

    public function status(): ?WorkStatus
    {
        $status = $this->validated('status');

        return $status === null ? null : WorkStatus::from($status);
    }

    /**
     * @return list<RatingFlag>
     */
    public function ratingFlags(): array
    {
        return array_map(RatingFlag::from(...), $this->validated('rating_flags', []));
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->validated('tags', []);
    }
}
