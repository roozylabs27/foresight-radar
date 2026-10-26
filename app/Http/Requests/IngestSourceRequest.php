<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngestSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:30', 'max:'.$this->maxChars()],
            'url' => ['nullable', 'url', 'max:2048'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.max' => 'Dokumen terlalu panjang (maks. '.number_format($this->maxChars(), 0, ',', '.').' karakter). Pecah menjadi beberapa bagian dan ingest satu per satu.',
        ];
    }

    protected function maxChars(): int
    {
        return (int) config('services.signal_extraction.max_chars', 60000);
    }
}
