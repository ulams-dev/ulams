<?php

namespace Ulams\Identity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Identity\Support\Pkce;

class PkceTest extends TestCase
{
    public function testTheChallengeIsTheS256OfTheVerifierPerRfc7636(): void
    {
        // RFC 7636 appendix B
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', Pkce::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));
    }

    public function testVerifiersAreRandomAndWithinTheAllowedAlphabetAndLength(): void
    {
        $a = Pkce::verifier();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $a);
        $this->assertNotSame($a, Pkce::verifier());
    }
}
