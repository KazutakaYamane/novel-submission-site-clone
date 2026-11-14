<?php

namespace App\Http\Requests\PublicWorks;

use Illuminate\Foundation\Http\FormRequest;

class ListPublicWorksRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }

    public function limit(): int
    {
        return $this->integer('limit', 20);
    }
}
