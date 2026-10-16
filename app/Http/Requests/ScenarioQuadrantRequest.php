<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScenarioQuadrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-scenario') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'narrative' => ['required', 'string', 'max:5000'],
            'implications' => ['nullable', 'string', 'max:3000'],
            'early_indicators' => ['nullable', 'array', 'max:8'],
            'early_indicators.*' => ['string', 'max:300'],
        ];
    }
}
