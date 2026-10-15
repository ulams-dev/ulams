<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use Ulams\CourseBuilder\Ingestion\TextEncoding;
use Ulams\CourseBuilder\Tests\TestCase;

class TextEncodingTest extends TestCase
{
    public function testInvalidPdfBytesBecomeJsonSafeUtf8(): void
    {
        $clean = TextEncoding::utf8(['text' => "microdose \xC3\x28 study", 'ok' => 'psilocybin — anxiety']);

        $this->assertTrue(mb_check_encoding($clean['text'], 'UTF-8'));
        $this->assertSame('psilocybin — anxiety', $clean['ok']);
        $this->assertNotFalse(json_encode($clean));
    }
}
