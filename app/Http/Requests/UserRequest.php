<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
        $validation = [];
        if (request('type') == 'create') {
            $validation = [
                'name' => ['required', 'string', 'min:3','max:50'],
                'email' => ['required','email', Rule::unique('users')],
                'password' => [
                    'required',
                    'min:6',
                    'max:50'
                ],
                'role_id' => 'required'
            ];
        } else {
            $validation = [
                'name' => ['required', 'string', 'max:255'],
                'email' => 'required|email',
                'role_id' => 'required'
            ];
        }
        return $validation;
    }
}
