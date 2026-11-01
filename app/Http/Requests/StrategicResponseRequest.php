<?php

namespace App\Http\Requests;

use App\Models\Scenario;
use App\Models\StrategicResponse;
use App\Support\TenantRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A strategic response (create or update). `fit` maps quadrant positions (HH, HL, LH, LL) to a
 * fit; whether the linked items belong to the exercise is checked by StrategicResponseService.
 */
class StrategicResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-scenario') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rationale' => ['nullable', 'string', 'max:2000'],
            'initiatives' => ['nullable', 'string', 'max:3000'],
            'priority' => ['nullable', 'integer', 'between:1,5'],
            'owner' => ['nullable', 'uuid', TenantRule::exists('users', 'uuid')],
            'depends_on' => ['nullable', 'string', 'max:1000'],
            'fit' => ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail) {
                if (array_diff(array_keys((array) $value), Scenario::POSITIONS) !== []) {
                    $fail('Kecocokan hanya untuk kuadran HH, HL, LH dan LL.');
                }
            }],
            'fit.*' => [Rule::in(StrategicResponse::FITS)],
            'implications' => ['nullable', 'array', 'max:30'],
            'implications.*' => ['uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul',
            'description' => 'deskripsi',
            'rationale' => 'alasan',
            'initiatives' => 'inisiatif',
            'priority' => 'prioritas',
            'owner' => 'penanggung jawab',
            'depends_on' => 'asumsi yang mendasari',
            'fit.*' => 'kecocokan',
        ];
    }
}
