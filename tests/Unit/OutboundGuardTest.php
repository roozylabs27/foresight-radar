<?php

namespace Tests\Unit;

use App\Support\Net\OutboundGuard;
use App\Support\Net\UnsafeOutboundUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeDnsResolver;

/**
 * Production readiness 2026-11-13: SSRF and DNS rebinding guard for user-configured AI base URLs.
 * DNS is controlled (FakeDnsResolver); no real name is resolved and no host is contacted.
 */
class OutboundGuardTest extends TestCase
{
    private FakeDnsResolver $dns;

    private OutboundGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dns = new FakeDnsResolver;
        $this->guard = new OutboundGuard($this->dns);
    }

    /** @return array<string, array{string}> */
    public static function forbiddenAddresses(): array
    {
        return [
            'private 10/8' => ['10.1.2.3'],
            'private 172.16/12' => ['172.20.0.5'],
            'private 192.168/16' => ['192.168.1.10'],
            'loopback' => ['127.0.0.1'],
            'loopback range' => ['127.255.0.9'],
            'link-local / cloud metadata' => ['169.254.169.254'],
            'this network' => ['0.0.0.0'],
            'carrier-grade NAT' => ['100.64.0.1'],
            'benchmarking' => ['198.18.0.1'],
            'documentation' => ['203.0.113.7'],
            'multicast' => ['224.0.0.1'],
            'reserved' => ['240.0.0.1'],
            'broadcast' => ['255.255.255.255'],
            'ipv6 loopback' => ['::1'],
            'ipv6 unspecified' => ['::'],
            'ipv6 unique local' => ['fd00::1'],
            'ipv6 link-local' => ['fe80::1'],
            'ipv6 multicast' => ['ff02::1'],
            'ipv4-mapped private' => ['::ffff:10.0.0.1'],
            'ipv4-mapped loopback' => ['::ffff:127.0.0.1'],
            'ipv4-mapped public' => ['::ffff:93.184.215.14'],
            'nat64' => ['64:ff9b::a00:1'],
            '6to4' => ['2002:a00:1::1'],
            'teredo' => ['2001:0:4136:e378::1'],
            'ipv6 documentation' => ['2001:db8::1'],
        ];
    }

    #[DataProvider('forbiddenAddresses')]
    public function test_a_forbidden_address_is_not_public(string $ip): void
    {
        $this->assertFalse(OutboundGuard::isPublicIp($ip));
    }

    public function test_public_addresses_are_public(): void
    {
        foreach (['93.184.215.14', '8.8.8.8', '1.1.1.1', '2606:4700:4700::1111', '2a00:1450:4001:80b::200e'] as $ip) {
            $this->assertTrue(OutboundGuard::isPublicIp($ip), $ip);
        }
    }

    #[DataProvider('forbiddenAddresses')]
    public function test_a_host_resolving_to_a_forbidden_address_is_refused(string $ip): void
    {
        $this->dns->answer('api.provider.example', [$ip]);

        $this->expectException(UnsafeOutboundUrl::class);
        $this->guard->target('https://api.provider.example/v1');
    }

    public function test_one_forbidden_answer_among_public_ones_refuses_the_host(): void
    {
        $this->dns->answer('mixed.provider.example', ['93.184.215.14', '10.0.0.5', '1.1.1.1']);

        $this->expectException(UnsafeOutboundUrl::class);
        $this->guard->target('https://mixed.provider.example/v1');
    }

    public function test_a_public_host_is_pinned_to_its_checked_ipv4_address(): void
    {
        $this->dns->answer('api.provider.example', ['2606:4700:4700::1111', '93.184.215.14']);

        $target = $this->guard->target('https://API.Provider.example/v1/');

        $this->assertSame(['api.provider.example', 443, '93.184.215.14'], [$target->host, $target->port, $target->ip]);
        $options = $target->guzzleOptions();
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame(['api.provider.example:443:93.184.215.14'], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertSame(OutboundGuard::MAX_RESPONSE_BYTES, $options['curl'][CURLOPT_MAXFILESIZE_LARGE]);
        $this->assertSame(CURLPROTO_HTTPS, $options['curl'][CURLOPT_PROTOCOLS]);
    }

    public function test_an_ipv6_only_host_is_pinned_with_brackets(): void
    {
        $this->dns->answer('v6.provider.example', ['2606:4700:4700::1111']);

        $this->assertSame(['v6.provider.example:443:[2606:4700:4700::1111]'], $this->guard->target('https://v6.provider.example')->guzzleOptions()['curl'][CURLOPT_RESOLVE]);
    }

    /**
     * DNS rebinding: the name answers public for the check and private afterwards. The guard resolves
     * once and pins that answer, so the later private answer is never used for this request.
     */
    public function test_a_rebinding_name_is_resolved_once_and_pinned(): void
    {
        $this->dns->answer('rebind.provider.example', ['93.184.215.14'], ['127.0.0.1']);

        $target = $this->guard->target('https://rebind.provider.example/v1');

        $this->assertSame('93.184.215.14', $target->ip);
        $this->assertSame(['rebind.provider.example'], $this->dns->queries);
        // The next check (a new request) sees the private answer and refuses it.
        $this->expectException(UnsafeOutboundUrl::class);
        $this->guard->target('https://rebind.provider.example/v1');
    }

    public function test_a_name_that_does_not_resolve_is_refused(): void
    {
        $this->dns->answer('nxdomain.provider.example', []);

        $this->expectExceptionMessage('DNS');
        $this->guard->target('https://nxdomain.provider.example/v1');
    }

    /** @return array<string, array{string}> */
    public static function badUrls(): array
    {
        return [
            'plain http' => ['http://api.provider.example/v1'],
            'ftp' => ['ftp://api.provider.example/v1'],
            'javascript' => ['javascript:alert(1)'],
            'not a url' => ['api.provider.example/v1'],
            'credentials in url' => ['https://user:pass@api.provider.example/v1'],
            'unexpected port' => ['https://api.provider.example:8443/v1'],
            'port 80 over https' => ['https://api.provider.example:80/v1'],
            'localhost' => ['https://localhost/v1'],
            'single label (docker service)' => ['https://laravel_app/v1'],
            'internal suffix' => ['https://llm.corp/v1'],
            'trailing dot' => ['https://api.provider.example./v1'],
            'private ip literal' => ['https://10.0.0.5/v1'],
            'ipv6 loopback literal' => ['https://[::1]/v1'],
            'mapped ipv6 literal' => ['https://[::ffff:127.0.0.1]/v1'],
        ];
    }

    #[DataProvider('badUrls')]
    public function test_a_bad_url_is_refused_before_any_dns_query(string $url): void
    {
        try {
            $this->guard->target($url);
            $this->fail("{$url} was accepted");
        } catch (UnsafeOutboundUrl) {
            $this->assertSame([], $this->dns->queries);
        }
    }

    public function test_a_public_ip_literal_needs_no_dns(): void
    {
        $target = $this->guard->target('https://93.184.215.14/v1');

        $this->assertSame('93.184.215.14', $target->ip);
        $this->assertSame([], $this->dns->queries);
    }
}
