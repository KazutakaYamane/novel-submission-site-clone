<?php

namespace App\Http\Requests\Demo;

use App\Domain\Demo\DemoRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartDemoSessionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(DemoRole::class)],
        ];
    }
}
