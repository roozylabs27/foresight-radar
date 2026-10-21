<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-dashboard') ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'string'],
            'dimension' => ['nullable'],
            'overrides' => ['required', 'array', 'max:50'],
            'overrides.*.force' => ['required', 'string', 'exists:driving_forces,uuid'],
            'overrides.*.status_action' => ['nullable', 'string', 'in:monitoring,decided_plan'],
            'overrides.*.impact' => ['nullable', 'integer', 'min:1', 'max:10'],
            'overrides.*.uncertainty' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}
