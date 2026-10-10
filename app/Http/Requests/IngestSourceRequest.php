<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngestSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:30'],
            'url' => ['nullable', 'url', 'max:2048'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
