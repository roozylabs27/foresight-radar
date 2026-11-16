<?php

namespace App\Support\Net;

/** A checked outbound destination: the host name (for TLS/SNI and Host) and the one IP the connection must use. */
final class OutboundTarget
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $ip,
    ) {}

    /**
     * Guzzle options that pin the connection to the checked IP. cURL's CURLOPT_RESOLVE pre-fills its DNS
     * cache for host:port, so the name is not resolved again (no DNS rebinding between check and connect),
     * while TLS still verifies the certificate for the host name. Redirects are refused (a redirect could
     * point anywhere), and the response body is capped.
     *
     * @return array<string, mixed>
     */
    public function guzzleOptions(): array
    {
        $ip = str_contains($this->ip, ':') ? "[{$this->ip}]" : $this->ip;

        return [
            'allow_redirects' => false,
            'curl' => [
                CURLOPT_RESOLVE => ["{$this->host}:{$this->port}:{$ip}"],
                CURLOPT_MAXFILESIZE_LARGE => OutboundGuard::MAX_RESPONSE_BYTES,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            ],
        ];
    }
}
