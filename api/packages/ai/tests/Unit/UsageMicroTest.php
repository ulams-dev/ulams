<?php

declare(strict_types=1);

namespace Ulams\Ai\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Ai\Dto\Usage;

final class UsageMicroTest extends TestCase
{
    #[Test]
    public function testDefaultValues()
    {
        $usage = new Usage();
        $this->assertEquals(0, $usage->inputTokens);
        $this->assertEquals(0, $usage->outputTokens);
        $this->assertEquals(0, $usage->cacheWrite5mTokens);
        $this->assertEquals(0, $usage->cacheWrite1hTokens);
        $this->assertEquals(0, $usage->cacheReadTokens);
    }

    #[Test]
    public function testCacheWriteTokens()
    {
        $usage = new Usage(cacheWrite5mTokens: 10, cacheWrite1hTokens: 20);
        $this->assertEquals(30, $usage->cacheWriteTokens());
    }

    #[Test]
    public function testTotalInputTokens()
    {
        $usage = new Usage(inputTokens: 10, cacheWrite5mTokens: 10, cacheWrite1hTokens: 20, cacheReadTokens: 5);
        $this->assertEquals(45, $usage->totalInputTokens());
    }

    #[Test]
    public function testPlusMethod()
    {
        $usage1 = new Usage(inputTokens: 10, outputTokens: 20, cacheWrite5mTokens: 30, cacheWrite1hTokens: 40, cacheReadTokens: 50);
        $usage2 = new Usage(inputTokens: 5, outputTokens: 15, cacheWrite5mTokens: 25, cacheWrite1hTokens: 35, cacheReadTokens: 45);
        $result = $usage1->plus($usage2);
        $this->assertEquals(15, $result->inputTokens);
        $this->assertEquals(35, $result->outputTokens);
        $this->assertEquals(55, $result->cacheWrite5mTokens);
        $this->assertEquals(75, $result->cacheWrite1hTokens);
        $this->assertEquals(95, $result->cacheReadTokens);
    }

    #[Test]
    public function testToArray()
    {
        $usage = new Usage(inputTokens: 10, outputTokens: 20, cacheWrite5mTokens: 30, cacheWrite1hTokens: 40, cacheReadTokens: 50);
        $array = $usage->toArray();
        $this->assertEquals([
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cache_write_5m_tokens' => 30,
            'cache_write_1h_tokens' => 40,
            'cache_read_tokens' => 50,
        ], $array);
    }

    #[Test]
    public function testFromArrayWithLegacyKey()
    {
        $array = [
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cache_write_5m_tokens' => 30,
            'cache_creation_tokens' => 40,
            'cache_read_tokens' => 50,
        ];
        $usage = Usage::fromArray($array);
        $this->assertEquals(10, $usage->inputTokens);
        $this->assertEquals(20, $usage->outputTokens);
        $this->assertEquals(30, $usage->cacheWrite5mTokens);
        $this->assertEquals(40, $usage->cacheWrite1hTokens);
        $this->assertEquals(50, $usage->cacheReadTokens);
    }
}
