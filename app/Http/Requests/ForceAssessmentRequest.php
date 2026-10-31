<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The consensus score of one driving force in an exercise (step 3). */
class ForceAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-scenario') ?? false;
    }

    public function rules(): array
    {
        return [
            'impact' => ['required', 'integer', 'between:1,10'],
            'uncertainty' => ['required', 'integer', 'between:1,10'],
            'impact_rationale' => ['nullable', 'string', 'max:1000'],
            'uncertainty_rationale' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'impact' => 'impact',
            'uncertainty' => 'uncertainty',
            'impact_rationale' => 'alasan impact',
            'uncertainty_rationale' => 'alasan uncertainty',
        ];
    }
}
