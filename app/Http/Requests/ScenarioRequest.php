<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-scenario') ?? false;
    }

    public function rules(): array
    {
        $label = ['required', 'string', 'max:120'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'focal_question' => ['nullable', 'string', 'max:2000'],
            'axis_a' => ['required', 'uuid', 'exists:driving_forces,uuid'],
            'axis_b' => ['required', 'uuid', 'different:axis_a', 'exists:driving_forces,uuid'],
            'axis_a_low_label' => $label,
            'axis_a_high_label' => $label,
            'axis_b_low_label' => $label,
            'axis_b_high_label' => $label,
            'status' => ['sometimes', Rule::in(['DRAFT', 'FINAL'])],
        ];
    }
}
