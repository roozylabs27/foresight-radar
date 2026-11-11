<?php

namespace App\Http\Requests;

use App\Models\DrivingForce;
use App\Rules\WordCountRule;
use App\Support\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
    /** A force description explains the force and its mechanism (roadmap R1.2); the keyword stays at four words. */
    public const DESCRIPTION_WORDS = 120;

    public function rules(): array
    {
        if (request()->isMethod('POST')) {
            return [
                'keyword' => ['required', 'string', new WordCountRule(4)],
                'description' => ['required', 'string', new WordCountRule(self::DESCRIPTION_WORDS)],
                'dimension_id' => ['required', TenantRule::activeDimension()],
                'pic_id' => ['required', TenantRule::exists('users')],
            ];
        } else {
            return [
                'keyword' => ['required', 'string', new WordCountRule(4)],
                'description' => ['required', 'string', new WordCountRule(self::DESCRIPTION_WORDS)],
                // An edit may keep the force on its (possibly archived) dimension.
                'dimension_id' => ['required', TenantRule::activeDimension($this->currentDimensionId())],
                'pic_id' => ['required', TenantRule::exists('users')],
            ];
        }
    }

    /** The dimension the edited force sits on, which it may keep even when archived. */
    private function currentDimensionId(): ?int
    {
        $force = $this->route('driving_force');

        return $force instanceof DrivingForce ? $force->dimension_id : null;
    }
}
