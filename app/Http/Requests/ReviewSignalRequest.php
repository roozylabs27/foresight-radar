<?php

namespace App\Http\Requests;

use App\Rules\WordCountRule;
use App\Support\TenantRule;
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
            'driving_force_id' => ['required_if:action,accept_link', 'nullable', TenantRule::exists('driving_forces')],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
            // accept_create (roadmap R1.3): the force gets its own name; required by the controller after
            // the status and evidence checks.
            'force_keyword' => ['nullable', 'string', 'max:100', new WordCountRule(4)],
            'force_description' => ['nullable', 'string', 'max:2000'],
            'confirm_signal_title' => ['boolean'],
            'attach_signals' => ['nullable', 'array', 'max:50'],
            'attach_signals.*' => ['uuid'],
        ];
    }
}
