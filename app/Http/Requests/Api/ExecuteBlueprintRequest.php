<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class ExecuteBlueprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'revision_id' => [
                'required',
                'string',
                'ulid',
            ],
            'input' => [
                'nullable',
                'array',
            ],
            'context' => [
                'nullable',
                'array',
            ],
        ];
    }
}