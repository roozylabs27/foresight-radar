<?php

namespace App\Http\Requests;

use App\Rules\DifferentPole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A critical uncertainty proposal (create or update). Whether the forces
 * belong to the exercise and qualify is checked by CriticalUncertaintyService.
 */
class CriticalUncertaintyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-scenario') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'forces' => ['required', 'array', 'min:1', 'max:20'],
            'forces.*' => ['required', 'uuid'],
            'low_pole_label' => ['required', 'string', 'max:120'],
            'high_pole_label' => ['required', 'string', 'max:120', new DifferentPole('low_pole_label')],
            'selection_rationale' => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'forces' => 'driving force',
            'low_pole_label' => 'kutub rendah',
            'high_pole_label' => 'kutub tinggi',
            'selection_rationale' => 'alasan pemilihan',
        ];
    }
}
