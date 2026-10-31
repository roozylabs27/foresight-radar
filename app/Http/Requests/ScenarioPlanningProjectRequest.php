<?php

namespace App\Http\Requests;

use App\Models\ScenarioPlanningProject;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScenarioPlanningProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-scenario') ?? false;
    }

    public function rules(): array
    {
        $optionalText = ['nullable', 'string', 'max:2000'];
        $optionalLine = ['nullable', 'string', 'max:150'];

        return [
            'name' => ['required', 'string', 'max:150'],
            'focal_question' => ['required', 'string', 'min:10', 'max:500'],
            'purpose' => ['required', 'string', 'max:1000'],
            // The method looks at a medium-to-long horizon, so the target year lies ahead.
            'target_year' => ['required', 'integer', 'min:'.(now()->year + 1), 'max:2100'],
            'geography' => $optionalLine,
            'industry' => $optionalLine,
            'owner_unit' => $optionalLine,
            'owner' => ['nullable', 'uuid', TenantRule::exists('users', 'uuid')],
            'background' => $optionalText,
            'objectives' => $optionalText,
            'status' => ['sometimes', Rule::in(ScenarioPlanningProject::STATUSES)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama latihan',
            'focal_question' => 'pertanyaan fokus',
            'purpose' => 'tujuan',
            'target_year' => 'tahun target',
            'owner' => 'pemilik',
        ];
    }
}
