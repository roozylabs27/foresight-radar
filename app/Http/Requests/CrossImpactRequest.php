<?php

namespace App\Http\Requests;

use App\Models\CrossImpact;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class CrossImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-cross-impact') ?? false;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'uuid', TenantRule::exists('driving_forces', 'uuid')],
            'target' => ['required', 'uuid', 'different:source', TenantRule::exists('driving_forces', 'uuid')],
            'score' => ['required', 'integer', 'between:'.CrossImpact::MIN_SCORE.','.CrossImpact::MAX_SCORE],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
