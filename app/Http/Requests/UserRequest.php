<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

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
        $roleRule = [
            'required',
            'exists:roles,id',
            function ($attribute, $value, $fail) {
                $role = Role::find($value);
                if ($role && in_array($role->name, ['super-admin', 'developer'])) {
                    if (!auth()->user() || !auth()->user()->hasAnyRole(['super-admin', 'developer'])) {
                        $fail('You are not authorized to assign this privileged role.');
                    }
                }
            },
        ];

        if (request('type') == 'create') {
            return [
                'name' => ['required', 'string', 'min:3', 'max:50'],
                'email' => ['required', 'email', Rule::unique('users')],
                'password' => ['required', 'min:6', 'max:50'],
                'role_id' => $roleRule,
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->route('user'))],
            'role_id' => $roleRule,
        ];
    }
}
