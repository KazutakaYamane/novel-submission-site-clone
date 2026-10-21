<?php

namespace App\Http\Requests\MyWorks;

use App\Http\Rules\MaxBytes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class StoreEpisodeRequest extends FormRequest
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
        // 本文が空の下書きも保存できるようにし、空かどうかは公開するときに確かめる
        // (要素の上のコメントは Scramble が description に出すので、ここに書く)
        return [
            'title' => ['required', 'string', 'max:'.Config::integer('work.episode_max_length.title')],
            'body' => ['nullable', 'string', new MaxBytes(Config::integer('work.episode_body_max_bytes'))],
        ];
    }
}
