<?php

namespace App\Http\Requests;

use App\Rules\WordCountRule;
use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class DrivingForceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (request()->isMethod('POST')) {
            return [
                'keyword' => ['required', 'string', new WordCountRule(4)],
                'description' => ['required', 'string', new WordCountRule(20)],
                'dimension_id' => ['required', 'exists:dimensions,id'],
                'pic_id' => ['required', $this->picExistsRule()],
            ];
        } else {
            return [
                'keyword' => ['required', 'string', new WordCountRule(4)],
                'description' => ['required', 'string', new WordCountRule(30)],
                'dimension_id' => ['required', 'exists:dimensions,id'],
                'pic_id' => ['required', $this->picExistsRule()],
            ];
        }
    }

    /**
     * A PIC must belong to the caller's tenant; users carry no tenant scope of their own.
     */
    private function picExistsRule(): Exists
    {
        $tenantId = app(TenantContext::class)->getTenantId();

        return Rule::exists('users', 'id')->when($tenantId !== null, fn (Exists $rule) => $rule->where('tenant_id', $tenantId));
    }
}
