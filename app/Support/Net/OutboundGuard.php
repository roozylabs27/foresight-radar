<?php

namespace App\Support\Net;

/**
 * SSRF and DNS rebinding guard for outbound requests to URLs that users or organisations configure
 * (production readiness 2026-11-13; AI provider base URLs).
 *
 * `target()` accepts only https on port 443 without credentials, with a public host name or a public IP
 * literal. A name is resolved once; **every** address it resolves to must be public (one private answer
 * rejects the URL, so a name that mixes public and internal addresses cannot slip through). The caller
 * connects to the returned IP only (OutboundTarget::guzzleOptions pins it with CURLOPT_RESOLVE), so a
 * second, different DNS answer at connect time is never used. Redirects are refused there as well.
 *
 * IPv4 embedded in IPv6 (IPv4-mapped ::ffff:0:0/96, IPv4-compatible ::/96, NAT64 64:ff9b::/96, 6to4
 * 2002::/16, Teredo) is refused outright: a public provider has no reason to publish such an address,
 * and it is a common way around IPv4 filters.
 */
class OutboundGuard
{
    /** Largest response body accepted from such a host. */
    public const MAX_RESPONSE_BYTES = 5 * 1024 * 1024;

    private const ALLOWED_PORT = 443;

    private const INTERNAL_SUFFIXES = ['.localhost', '.local', '.internal', '.lan', '.home', '.corp', '.intranet', '.home.arpa'];

    /** IPv4 ranges that are not public unicast (RFC 6890 and friends). */
    private const BLOCKED_V4 = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ];

    /** IPv6 ranges that are not public unicast, including every form that embeds an IPv4 address. */
    private const BLOCKED_V6 = [
        '::/96',          // unspecified, loopback, IPv4-compatible
        '::ffff:0:0/96',  // IPv4-mapped
        '64:ff9b::/96', '64:ff9b:1::/48', // NAT64
        '100::/64',       // discard
        '2001::/23',      // IETF protocol assignments, Teredo (2001::/32)
        '2001:db8::/32',  // documentation
        '2002::/16',      // 6to4
        'fc00::/7',       // unique local
        'fe80::/10',      // link-local
        'fec0::/10',      // site-local (deprecated)
        'ff00::/8',       // multicast
    ];

    public function __construct(private readonly DnsResolver $resolver) {}

    /**
     * @throws UnsafeOutboundUrl with a message that can be shown to the user
     */
    public function target(string $url): OutboundTarget
    {
        $message = 'Alamat API harus berupa URL https publik (bukan alamat lokal atau jaringan internal).';
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new UnsafeOutboundUrl($message);
        }

        $parts = parse_url($url);
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeOutboundUrl($message);
        }
        $port = (int) ($parts['port'] ?? self::ALLOWED_PORT);
        if ($port !== self::ALLOWED_PORT) {
            throw new UnsafeOutboundUrl('Alamat API harus memakai port https standar (443).');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            if (! self::isPublicIp($literal)) {
                throw new UnsafeOutboundUrl($message);
            }

            return new OutboundTarget($literal, $port, $literal);
        }

        $host = $this->normaliseHost($host);
        if ($host === null) {
            throw new UnsafeOutboundUrl($message);
        }

        $addresses = $this->resolver->resolve($host);
        if ($addresses === []) {
            throw new UnsafeOutboundUrl('Alamat API tidak dapat ditemukan (DNS). Periksa nama host.');
        }
        foreach ($addresses as $address) {
            if (! self::isPublicIp($address)) {
                throw new UnsafeOutboundUrl($message);
            }
        }

        // Prefer IPv4 (the client used to force IPv4), else the first IPv6 address.
        $ipv4 = array_values(array_filter($addresses, fn (string $address) => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false));

        return new OutboundTarget($host, $port, $ipv4[0] ?? $addresses[0]);
    }

    /** True for a public unicast IPv4 or IPv6 address. */
    public static function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        $ranges = strlen($packed) === 4 ? self::BLOCKED_V4 : self::BLOCKED_V6;
        foreach ($ranges as $range) {
            if (self::inRange($packed, $range)) {
                return false;
            }
        }

        return true;
    }

    /** A DNS name that may be resolved, in ASCII form, or null when it is local or malformed. */
    private function normaliseHost(string $host): ?string
    {
        if ($host === '' || str_ends_with($host, '.')) {
            return null;
        }
        if (preg_match('/[^\x20-\x7e]/', $host)) {
            $ascii = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;
            if ($ascii === false) {
                return null;
            }
            $host = strtolower($ascii);
        }
        if (! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $host)) {
            return null;
        }
        if ($host === 'localhost') {
            return null;
        }
        foreach (self::INTERNAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return null;
            }
        }

        return $host;
    }

    private static function inRange(string $packed, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $networkPacked = inet_pton($network);
        if ($networkPacked === false || strlen($networkPacked) !== strlen($packed)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($packed, 0, $bytes) !== substr($networkPacked, 0, $bytes)) {
            return false;
        }
        $remainder = $bits % 8;
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($networkPacked[$bytes]) & $mask);
    }
}
