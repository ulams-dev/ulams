<?php

namespace Ulams\Ai\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Services\CostCalculator;

class CostCalculatorTest extends TestCase
{
    private function calc(): CostCalculator
    {
        return new CostCalculator([
            'sonnet' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.20],
            'haiku' => ['input' => 0.10, 'output' => 0.50, 'cache_read' => 0.01,
                'long_context' => ['above' => 100000, 'input' => 0.50, 'output' => 2.50, 'cache_read' => 0.05]],
        ]);
    }

    public function testInputAndOutput(): void
    {
        // 1M input × $2 + 100k output × $10 = $3
        $this->assertSame(3000000, $this->calc()->cost('sonnet', new Usage(1000000, 100000)));
    }

    public function testCacheWritesAndReads(): void
    {
        // 10k 1h writes × 2 × $2/M = 0.04; 10k 5m writes × 1.25 × $2/M = 0.025; 100k reads × 0.20/M = 0.02
        $usage = new Usage(0, 0, 10000, 10000, 100000);
        $this->assertSame(85000, $this->calc()->cost('sonnet', $usage));
    }

    public function testLongContextTierAppliesAboveThreshold(): void
    {
        $below = $this->calc()->cost('haiku', new Usage(100000, 1000));
        $above = $this->calc()->cost('haiku', new Usage(100001, 1000));
        $this->assertSame(10500, $below);           // 100k × 0.10 + 1k × 0.50
        $this->assertSame(52501, $above);           // 100001 × 0.50 + 1k × 2.50 = 52500.5, rounded
        // cached tokens count toward the threshold
        $this->assertSame(30500, $this->calc()->cost('haiku', new Usage(50000, 1000, 0, 0, 60000)));
    }

    public function testUnknownModelCostsNothingAndPrefixMatches(): void
    {
        $this->assertSame(0, $this->calc()->cost('other', new Usage(1000, 1000)));
        $this->assertSame(12000, $this->calc()->cost('sonnet-20991231', new Usage(1000, 1000)));
    }
}
