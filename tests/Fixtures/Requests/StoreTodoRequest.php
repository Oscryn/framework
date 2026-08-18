<?php

declare(strict_types=1);

namespace Tests\Fixtures\Requests;

use Oscryn\Http\FormRequest;

class StoreTodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'     => 'required|min:3|max:255',
            'completed' => 'boolean',
        ];
    }
}
