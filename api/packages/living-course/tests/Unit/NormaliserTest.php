<?php

namespace Ulams\LivingCourse\Tests\Unit;

use Ulams\LivingCourse\Diff\Normaliser;
use Ulams\LivingCourse\Tests\TestCase;

class NormaliserTest extends TestCase
{
    public function testWhitespaceEmphasisQuotesAndDashesAreNotChanges(): void
    {
        $a = "The **brew** ratio is  \"1:16\" \u{2014} a good start.\r\nSecond   line.";
        $b = "The brew ratio is \u{201C}1:16\u{201D} - a good start. Second line.";
        $this->assertSame(Normaliser::text($a), Normaliser::text($b));
        $this->assertSame(Normaliser::hash($a), Normaliser::hash($b));
    }

    public function testUnderscoresInIdentifiersAreKeptButEmphasisUnderscoresGo(): void
    {
        $this->assertSame('Use max_retries and bold.', Normaliser::text('Use max_retries and _bold_.'));
    }

    public function testCodeIsComparedAsWritten(): void
    {
        $a = "Run:\n\n```sh\nnpm   run  build\n```\n";
        $b = "Run:\n\n```sh\nnpm run build\n```\n";
        $this->assertNotSame(Normaliser::hash($a), Normaliser::hash($b));
        $this->assertNotSame(Normaliser::text('Use `a  b`.'), Normaliser::text('Use `a b`.'));
    }

    public function testRealChangesChangeTheHash(): void
    {
        $this->assertNotSame(Normaliser::hash('Use a ratio of 1:16.'), Normaliser::hash('Use a ratio of 1:15.'));
    }
}
