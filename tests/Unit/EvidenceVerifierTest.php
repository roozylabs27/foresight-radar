<?php

namespace Tests\Unit;

use App\Support\EvidenceVerifier;
use PHPUnit\Framework\TestCase;

class EvidenceVerifierTest extends TestCase
{
    private const SOURCE = "The ministry enacted the Euro-5 emission standard for all new vehicles.\n\nImporters   must comply\nby January 2027, according to the decree.";

    public function test_exact_quote_is_verified(): void
    {
        $this->assertTrue(EvidenceVerifier::verify('The ministry enacted the Euro-5 emission standard for all new vehicles.', self::SOURCE));
    }

    public function test_whitespace_differences_are_ignored(): void
    {
        $this->assertTrue(EvidenceVerifier::verify('Importers must comply by January 2027, according to the decree.', self::SOURCE));
        $this->assertTrue(EvidenceVerifier::verify("  Importers\u{00A0}must comply by January 2027  ", self::SOURCE));
    }

    public function test_case_paraphrase_and_ellipsis_are_rejected(): void
    {
        $this->assertFalse(EvidenceVerifier::verify('the ministry enacted the euro-5 emission standard', self::SOURCE));
        $this->assertFalse(EvidenceVerifier::verify('The ministry adopted the Euro-5 emission standard for all new vehicles.', self::SOURCE));
        $this->assertFalse(EvidenceVerifier::verify('The ministry enacted ... for all new vehicles.', self::SOURCE));
    }

    public function test_short_or_missing_quotes_are_rejected(): void
    {
        $this->assertFalse(EvidenceVerifier::verify('Euro-5 emission', self::SOURCE));
        $this->assertFalse(EvidenceVerifier::verify(null, self::SOURCE));
        $this->assertFalse(EvidenceVerifier::verify('The ministry enacted the Euro-5 emission standard', null));
    }
}
