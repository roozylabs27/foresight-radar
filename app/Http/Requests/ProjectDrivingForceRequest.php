<?php

namespace App\Http\Requests;

use App\Models\ProjectDrivingForce;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding library forces to an exercise (POST) or changing one force's
 * per-exercise fields (PUT).
 */
class ProjectDrivingForceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-scenario') ?? false;
    }

    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'forces' => ['required', 'array', 'min:1', 'max:100'],
                // Own tenant only, and not soft-deleted.
                'forces.*' => ['required', 'uuid', TenantRule::exists('driving_forces', 'uuid')->whereNull('deleted_at')],
            ];
        }

        return [
            'inclusion_status' => ['sometimes', Rule::in(ProjectDrivingForce::STATUSES)],
            'influence_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_predetermined' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'forces' => 'driving force',
            'forces.*' => 'driving force',
            'inclusion_status' => 'status',
            'influence_note' => 'catatan pengaruh',
        ];
    }
}
