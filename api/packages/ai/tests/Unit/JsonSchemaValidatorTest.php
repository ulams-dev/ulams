<?php

namespace Ulams\Ai\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Ai\Services\JsonSchemaValidator;

class JsonSchemaValidatorTest extends TestCase
{
    private const SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['items'],
        'properties' => [
            'items' => [
                'type' => 'array',
                'minItems' => 2,
                'maxItems' => 5,
                'items' => ['type' => 'string', 'minLength' => 2, 'pattern' => '^[a-z]+$', 'format' => 'slug'],
            ],
            'when' => ['type' => 'string', 'format' => 'date-time'],
            'n' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 9],
        ],
    ];

    public function testReportsPathsOfErrors(): void
    {
        $errors = (new JsonSchemaValidator())->validate(['items' => ['ok', 'X'], 'extra' => 1], self::SCHEMA);
        $this->assertNotEmpty($errors);
        $joined = implode("\n", $errors);
        $this->assertStringContainsString('/items/1', $joined);
        $this->assertStringContainsString('extra', $joined);
    }

    public function testValidData(): void
    {
        $this->assertSame([], (new JsonSchemaValidator())->validate(['items' => ['ab', 'cd'], 'n' => 3], self::SCHEMA));
    }

    public function testProviderSchemaDropsUnsupportedKeywords(): void
    {
        $out = JsonSchemaValidator::forProvider(self::SCHEMA);
        $items = $out['properties']['items'];
        $this->assertSame(1, $items['minItems']);
        $this->assertArrayNotHasKey('maxItems', $items);
        $this->assertSame(['type' => 'string'], $items['items']);
        $this->assertSame('date-time', $out['properties']['when']['format']);
        $this->assertSame(['type' => 'integer'], $out['properties']['n']);
        $this->assertFalse($out['additionalProperties']);
    }
}
