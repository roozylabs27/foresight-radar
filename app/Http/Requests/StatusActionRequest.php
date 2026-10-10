<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatusActionRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status_action_id' => ['required', 'exists:status_actions,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
