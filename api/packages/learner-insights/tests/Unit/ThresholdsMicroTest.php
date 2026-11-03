<?php

declare(strict_types=1);

namespace Ulams\LearnerInsights\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LearnerInsights\Scoring\Thresholds;

final class ThresholdsMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testConstructor(): void
    {
        $rules = [
            'rule1' => ['enabled' => true, 'param1' => 10],
            'rule2' => ['enabled' => false, 'param2' => 20],
        ];
        $thresholds = new Thresholds($rules);
        $this->assertEquals($rules, $thresholds->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testMakeWithNoOverrides(): void
    {
        $tenantRules = [
            'rule1' => ['enabled' => true, 'param1' => 10],
            'rule2' => ['enabled' => false, 'param2' => 20],
        ];
        $thresholds = Thresholds::make($tenantRules);
        $this->assertEquals($tenantRules, $thresholds->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testMakeWithCourseOverrides(): void
    {
        $tenantRules = [
            'rule1' => ['enabled' => true, 'param1' => 10],
            'rule2' => ['enabled' => false, 'param2' => 20],
        ];
        $courseOverrides = [
            'rule1' => ['param1' => 15],
            'rule3' => ['param3' => 30], // unknown rule
        ];
        $expectedRules = [
            'rule1' => ['enabled' => true, 'param1' => 15],
            'rule2' => ['enabled' => false, 'param2' => 20],
        ];
        $thresholds = Thresholds::make($tenantRules, $courseOverrides);
        $this->assertEquals($expectedRules, $thresholds->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testEnabledTrue(): void
    {
        $rules = [
            'rule1' => ['enabled' => true, 'param1' => 10],
        ];
        $thresholds = new Thresholds($rules);
        $this->assertTrue($thresholds->enabled('rule1'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testEnabledFalse(): void
    {
        $rules = [
            'rule1' => ['enabled' => false, 'param1' => 10],
        ];
        $thresholds = new Thresholds($rules);
        $this->assertFalse($thresholds->enabled('rule1'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testEnabledMissing(): void
    {
        $rules = [
            'rule1' => ['param1' => 10],
        ];
        $thresholds = new Thresholds($rules);
        $this->assertFalse($thresholds->enabled('rule1'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGet(): void
    {
        $rules = [
            'rule1' => ['param1' => 10],
        ];
        $thresholds = new Thresholds($rules);
        $this->assertEquals(10, $thresholds->get('rule1', 'param1'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAll(): void
    {
        $rules = [
            'rule1' => ['enabled' => true, 'param1' => 10],
            'rule2' => ['enabled' => false, 'param2' => 20],
        ];
        $thresholds = new Thresholds($rules);
        $this->assertEquals($rules, $thresholds->all());
    }
}
