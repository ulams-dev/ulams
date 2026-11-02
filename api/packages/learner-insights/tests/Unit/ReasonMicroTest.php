<?php

declare(strict_types=1);

namespace Ulams\LearnerInsights\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LearnerInsights\Scoring\Reason;

final class ReasonMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testConstructorDefaults(): void
    {
        $reason = new Reason('rule1', 'message.key');
        $this->assertEquals('rule1', $reason->rule);
        $this->assertEquals('message.key', $reason->messageKey);
        $this->assertEquals([], $reason->params);
        $this->assertNull($reason->elementKey);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testConstructorWithParamsAndElementKey(): void
    {
        $reason = new Reason('rule2', 'message.key', ['param1' => 'value1'], 'element1');
        $this->assertEquals('rule2', $reason->rule);
        $this->assertEquals('message.key', $reason->messageKey);
        $this->assertEquals(['param1' => 'value1'], $reason->params);
        $this->assertEquals('element1', $reason->elementKey);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testToArrayWithoutElementKey(): void
    {
        $reason = new Reason('rule3', 'message.key');
        $array = $reason->toArray();
        $this->assertEquals(['rule' => 'rule3', 'messageKey' => 'message.key', 'params' => [], 'elementKey' => null], $array);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testToArrayWithElementKey(): void
    {
        $reason = new Reason('rule4', 'message.key', ['param1' => 'value1'], 'element2');
        $array = $reason->toArray();
        $this->assertEquals(['rule' => 'rule4', 'messageKey' => 'message.key', 'params' => ['param1' => 'value1'], 'elementKey' => 'element2'], $array);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testFromArrayWithoutElementKey(): void
    {
        $array = ['rule' => 'rule5', 'messageKey' => 'message.key', 'params' => []];
        $reason = Reason::fromArray($array);
        $this->assertEquals('rule5', $reason->rule);
        $this->assertEquals('message.key', $reason->messageKey);
        $this->assertEquals([], $reason->params);
        $this->assertNull($reason->elementKey);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testFromArrayWithElementKey(): void
    {
        $array = ['rule' => 'rule6', 'messageKey' => 'message.key', 'params' => ['param1' => 'value1'], 'elementKey' => 'element3'];
        $reason = Reason::fromArray($array);
        $this->assertEquals('rule6', $reason->rule);
        $this->assertEquals('message.key', $reason->messageKey);
        $this->assertEquals(['param1' => 'value1'], $reason->params);
        $this->assertEquals('element3', $reason->elementKey);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRoundTripWithoutElementKey(): void
    {
        $reason = new Reason('rule7', 'message.key');
        $array = $reason->toArray();
        $reasonFromArray = Reason::fromArray($array);
        $this->assertEquals($reason, $reasonFromArray);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRoundTripWithElementKey(): void
    {
        $reason = new Reason('rule8', 'message.key', ['param1' => 'value1'], 'element4');
        $array = $reason->toArray();
        $reasonFromArray = Reason::fromArray($array);
        $this->assertEquals($reason, $reasonFromArray);
    }
}
