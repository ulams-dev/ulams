<?php

declare(strict_types=1);

namespace Ulams\Ai\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Ai\Dto\LlmResult;
use Ulams\Ai\Dto\Usage;

#[\PHPUnit\Framework\Attributes\TestSuite]
final class LlmResultMicroTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testConstructorSetsProperties(): void
    {
        $data = ['key' => 'value'];
        $model = 'test-model';
        $usage = new Usage(1, 2, 3, 4, 5);
        $costMicroUsd = 100;
        $attempts = 3;
        $callIds = ['id1', 'id2'];

        $llmResult = new LlmResult($data, $model, $usage, $costMicroUsd, $attempts, $callIds);

        $this->assertEquals($data, $llmResult->data);
        $this->assertEquals($model, $llmResult->model);
        $this->assertEquals($usage, $llmResult->usage);
        $this->assertEquals($costMicroUsd, $llmResult->costMicroUsd);
        $this->assertEquals($attempts, $llmResult->attempts);
        $this->assertEquals($callIds, $llmResult->callIds);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPropertiesAreReadonly(): void
    {
        $data = ['key' => 'value'];
        $model = 'test-model';
        $usage = new Usage(1, 2, 3, 4, 5);
        $costMicroUsd = 100;
        $attempts = 3;
        $callIds = ['id1', 'id2'];

        $llmResult = new LlmResult($data, $model, $usage, $costMicroUsd, $attempts, $callIds);

        $this->expectException(\Error::class);
        $llmResult->data = ['new' => 'value'];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUsagePropertyIsUsageObject(): void
    {
        $data = ['key' => 'value'];
        $model = 'test-model';
        $usage = new Usage(1, 2, 3, 4, 5);
        $costMicroUsd = 100;
        $attempts = 3;
        $callIds = ['id1', 'id2'];

        $llmResult = new LlmResult($data, $model, $usage, $costMicroUsd, $attempts, $callIds);

        $this->assertInstanceOf(Usage::class, $llmResult->usage);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCostMicroUsdPropertyIsInt(): void
    {
        $data = ['key' => 'value'];
        $model = 'test-model';
        $usage = new Usage(1, 2, 3, 4, 5);
        $costMicroUsd = 100;
        $attempts = 3;
        $callIds = ['id1', 'id2'];

        $llmResult = new LlmResult($data, $model, $usage, $costMicroUsd, $attempts, $callIds);

        $this->assertIsInt($llmResult->costMicroUsd);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAttemptsPropertyIsInt(): void
    {
        $data = ['key' => 'value'];
        $model = 'test-model';
        $usage = new Usage(1, 2, 3, 4, 5);
        $costMicroUsd = 100;
        $attempts = 3;
        $callIds = ['id1', 'id2'];

        $llmResult = new LlmResult($data, $model, $usage, $costMicroUsd, $attempts, $callIds);

        $this->assertIsInt($llmResult->attempts);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCallIdsPropertyIsArray(): void
    {
        $data = ['key' => 'value'];
        $model = 'test-model';
        $usage = new Usage(1, 2, 3, 4, 5);
        $costMicroUsd = 100;
        $attempts = 3;
        $callIds = ['id1', 'id2'];

        $llmResult = new LlmResult($data, $model, $usage, $costMicroUsd, $attempts, $callIds);

        $this->assertIsArray($llmResult->callIds);
    }
}
