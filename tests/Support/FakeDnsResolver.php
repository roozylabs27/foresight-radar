<?php

namespace Tests\Support;

use App\Support\Net\DnsResolver;

/**
 * Controlled DNS for tests: no test resolves real names. Unknown names answer with one public
 * documentation-free address, unless a test sets its own answers (also a sequence, to simulate rebinding).
 */
class FakeDnsResolver implements DnsResolver
{
    public const DEFAULT_PUBLIC_IP = '93.184.215.14';

    /** @var array<string, array<int, array<int, string>>> answers per host, consumed in order (the last one repeats) */
    private array $answers = [];

    /** @var array<int, string> */
    public array $queries = [];

    /** @param array<int, string> ...$answers one or more successive answers for the host */
    public function answer(string $host, array ...$answers): self
    {
        $this->answers[strtolower($host)] = $answers;

        return $this;
    }

    public function resolve(string $host): array
    {
        $host = strtolower($host);
        $this->queries[] = $host;
        if (! isset($this->answers[$host])) {
            return [self::DEFAULT_PUBLIC_IP];
        }

        return count($this->answers[$host]) > 1 ? array_shift($this->answers[$host]) : $this->answers[$host][0];
    }
}
