<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @method \App\Models\User|null user($guard = null)
 */
class ActivateUserLlmSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:anthropic,openai,gemini,openai_compatible'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
