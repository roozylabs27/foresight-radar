<?php

namespace App\Support\Net;

/**
 * Resolves a host name to its IP addresses (A and AAAA). Bound in the container, so tests can control
 * DNS answers (production readiness 2026-11-13, SSRF and DNS rebinding).
 */
interface DnsResolver
{
    /**
     * @return array<int, string> every IPv4 and IPv6 address the name resolves to; empty when it does not resolve
     */
    public function resolve(string $host): array;
}
