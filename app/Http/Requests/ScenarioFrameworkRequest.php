<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pole labels of an exercise framework (step 4): two different end states per axis.
 */
class ScenarioFrameworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-scenario') ?? false;
    }

    public function rules(): array
    {
        return [
            'axis_a_low_label' => ['required', 'string', 'max:120'],
            'axis_a_high_label' => ['required', 'string', 'max:120', 'different:axis_a_low_label'],
            'axis_b_low_label' => ['required', 'string', 'max:120'],
            'axis_b_high_label' => ['required', 'string', 'max:120', 'different:axis_b_low_label'],
        ];
    }

    public function attributes(): array
    {
        return [
            'axis_a_low_label' => 'kutub rendah sumbu A',
            'axis_a_high_label' => 'kutub tinggi sumbu A',
            'axis_b_low_label' => 'kutub rendah sumbu B',
            'axis_b_high_label' => 'kutub tinggi sumbu B',
        ];
    }
}
