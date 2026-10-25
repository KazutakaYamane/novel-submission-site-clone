<?php

namespace App\Http\Requests\MyWorks;

use App\Domain\Episode\EpisodeRepositoryInterface;
use App\Http\Rules\MaxBytes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type EpisodeChanges from EpisodeRepositoryInterface
 */
class UpdateEpisodeRequest extends FormRequest
{
    /**
     * 話の API は TrimStrings を外しているので(bootstrap/app.php)、タイトルだけここで削る
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => Str::trim($this->input('title'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // 空文字は ConvertEmptyStringsToNull で null になって届くので、本文の null は空文字として保存する
        // (要素の上のコメントは Scramble が description に出すので、ここに書く)
        return [
            'title' => ['sometimes', 'required', 'string', 'max:'.Config::integer('work.episode_max_length.title')],
            'body' => ['sometimes', 'nullable', 'string', new MaxBytes(Config::integer('work.episode_body_max_bytes'))],
        ];
    }

    /**
     * 送られた項目だけを返す
     *
     * @return EpisodeChanges
     */
    public function changes(): array
    {
        $validated = $this->validated();
        $changes = [];

        if (array_key_exists('title', $validated)) {
            $changes['title'] = $this->string('title')->toString();
        }
        if (array_key_exists('body', $validated)) {
            $changes['body'] = $this->string('body')->toString();
        }

        return $changes;
    }
}
