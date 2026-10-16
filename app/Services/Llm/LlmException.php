<?php

namespace App\Services\Llm;

use RuntimeException;
use Throwable;

/**
 * Why a structured call produced no usable answer. Callers fall back to
 * their non-AI path and show userMessage(); technical detail stays in logs.
 */
class LlmException extends RuntimeException
{
    public const REASONS = [
        'unavailable', 'rate_limited', 'overloaded', 'api_error',
        'timeout', 'refusal', 'max_tokens', 'invalid_json',
    ];

    public function __construct(public readonly string $reason, string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : $reason, 0, $previous);
    }

    public function userMessage(): string
    {
        return match ($this->reason) {
            'unavailable' => 'Layanan AI belum dikonfigurasi.',
            'rate_limited' => 'Kuota layanan AI sedang penuh. Coba lagi sebentar lagi.',
            'overloaded' => 'Layanan AI sedang sibuk. Coba lagi sebentar lagi.',
            'timeout' => 'Layanan AI tidak menjawab tepat waktu.',
            'refusal' => 'Layanan AI menolak memproses dokumen ini.',
            'max_tokens' => 'Jawaban AI terpotong karena terlalu panjang.',
            'invalid_json' => 'Jawaban AI tidak dapat dibaca.',
            default => 'Layanan AI sedang bermasalah.',
        };
    }
}
