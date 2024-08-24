<?php

namespace App\Http\Requests;

use App\Rules\WordCountRule;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (request()->isMethod("POST")) {
            return [
                'keyword' => ['required', 'string', new WordCountRule(4)],
                'description' => ['required', 'string', new WordCountRule(20)],
                'dimension_id' => ['required'],
                'pic_id' => ['required']
            ];
        } else {
            return [
                'keyword' => ['required', 'string', new WordCountRule(4)],
                'description' => ['required', 'string', new WordCountRule(30)],
                'dimension_id' => ['required'],
                'pic_id' => ['required']
            ];
        }
    }
}
