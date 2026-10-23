<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestUserLlmSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:anthropic,openai,gemini,openai_compatible'],
            'model' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'string', 'max:255'],
        ];
    }
}
