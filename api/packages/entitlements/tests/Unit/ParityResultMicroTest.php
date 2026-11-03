<?php

namespace Ulams\Entitlements\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Entitlements\Support\ParityResult;

class ParityResultMicroTest extends TestCase
{
    #[Test]
    public function testPassedWhenNoDifferences()
    {
        $result = new ParityResult(10, 0, ['kind1' => 0], []);
        $this->assertTrue($result->passed());
    }

    #[Test]
    public function testPassedWhenDifferencesExist()
    {
        $result = new ParityResult(10, 5, ['kind1' => 3, 'kind2' => 2], []);
        $this->assertFalse($result->passed());
    }

    #[Test]
    public function testDetailsReturnCorrectData()
    {
        $result = new ParityResult(10, 5, ['kind1' => 3, 'kind2' => 2], [['key1' => 'value1'], ['key2' => 'value2']]);
        $this->assertEquals(['by_kind' => ['kind1' => 3, 'kind2' => 2], 'sample' => [['key1' => 'value1'], ['key2' => 'value2']]], $result->details());
    }

    #[Test]
    public function testDetailsReturnEmptySample()
    {
        $result = new ParityResult(10, 0, [], []);
        $this->assertEquals(['by_kind' => [], 'sample' => []], $result->details());
    }

    #[Test]
    public function testPassedWithNoPairs()
    {
        $result = new ParityResult(0, 0, [], []);
        $this->assertTrue($result->passed());
    }

    #[Test]
    public function testPassedWithNoPairsAndDifferences()
    {
        $result = new ParityResult(0, 5, ['kind1' => 5], []);
        $this->assertFalse($result->passed());
    }

    #[Test]
    public function testDetailsWithNoPairs()
    {
        $result = new ParityResult(0, 0, [], []);
        $this->assertEquals(['by_kind' => [], 'sample' => []], $result->details());
    }

    #[Test]
    public function testDetailsWithNoPairsAndDifferences()
    {
        $result = new ParityResult(0, 5, ['kind1' => 5], [['key1' => 'value1']]);
        $this->assertEquals(['by_kind' => ['kind1' => 5], 'sample' => [['key1' => 'value1']]], $result->details());
    }
}
