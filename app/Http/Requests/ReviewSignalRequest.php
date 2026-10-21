<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['accept_create', 'accept_link', 'reject', 'update'])],
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'evidence_quote' => ['nullable', 'string'],
            'dimension_id' => ['nullable', 'exists:dimensions,id'],
            'suggested_time_horizon_id' => ['nullable', 'exists:time_horizons,id'],
            'preliminary_impact' => ['nullable', 'integer', 'between:1,10'],
            'preliminary_uncertainty' => ['nullable', 'integer', 'between:1,10'],
            'driving_force_id' => ['required_if:action,accept_link', 'nullable', 'exists:driving_forces,id'],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
        ];
    }
}
