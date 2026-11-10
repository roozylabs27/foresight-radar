<?php

namespace App\Http\Requests;

use App\Models\EarlyWarningIndicator;
use App\Models\Scenario;
use App\Services\ExternalIndicatorService;
use App\Support\TenantRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An early warning indicator (create or update). `directions` maps quadrant positions to
 * `{direction, note}`; links to the exercise are checked by EarlyWarningIndicatorService.
 */
class EarlyWarningIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-scenario') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'uncertainty' => ['nullable', 'uuid'],
            'force' => ['nullable', 'uuid'],
            'metric' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:40'],
            'baseline_value' => ['nullable', 'numeric'],
            'baseline_date' => ['nullable', 'date'],
            'frequency' => ['required', Rule::in(EarlyWarningIndicator::FREQUENCIES)],
            'owner' => ['nullable', 'uuid', TenantRule::exists('users', 'uuid')],
            'source_type' => ['required', Rule::in(EarlyWarningIndicator::SOURCE_TYPES)],
            // Roadmap R3.6: the connector, its series code and the area; kept only for external indicators.
            'external_source' => ['exclude_unless:source_type,external', 'required', Rule::in(array_keys(ExternalIndicatorService::SOURCES))],
            'external_series' => ['exclude_unless:source_type,external', 'required', 'string', 'max:60', 'regex:/^[A-Za-z0-9]+(\.[A-Za-z0-9_]+)*$/'],
            'external_area' => ['exclude_unless:source_type,external', 'required', 'string', 'regex:/^[A-Za-z]{2,3}$/'],
            'threshold_rule' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(EarlyWarningIndicator::STATUSES)],
            'directions' => ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail) {
                if (array_diff(array_keys((array) $value), Scenario::POSITIONS) !== []) {
                    $fail('Arah hanya untuk kuadran HH, HL, LH dan LL.');
                }
            }],
            'directions.*.direction' => ['required', Rule::in(EarlyWarningIndicator::DIRECTIONS)],
            'directions.*.note' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'description' => 'deskripsi',
            'frequency' => 'frekuensi',
            'owner' => 'penanggung jawab',
            'source_type' => 'sumber pemantauan',
            'external_source' => 'penyedia data',
            'external_series' => 'kode seri',
            'external_area' => 'kode wilayah',
            'threshold_rule' => 'ambang',
            'directions.*.direction' => 'arah',
        ];
    }
}
