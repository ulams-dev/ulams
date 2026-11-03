<?php

declare(strict_types=1);

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\Pipeline\PatchSchemas;
use InvalidArgumentException;

class PatchSchemasQwenTest extends TestCase
{
    public function testForValidType()
    {
        $schema = PatchSchemas::for('course');
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('$schema', $schema);
        $this->assertArrayHasKey('title', $schema);
        $this->assertArrayHasKey('type', $schema);
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertArrayHasKey('required', $schema);
        $this->assertArrayHasKey('properties', $schema);
    }

    public function testForInvalidTypeThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        PatchSchemas::for('invalidType');
    }

    public function testForCourseType()
    {
        $schema = PatchSchemas::for('course');
        $this->assertEquals('Patch of a course', $schema['title']);
        $this->assertArrayHasKey('replacement', $schema['properties']);
        $this->assertArrayHasKey('title', $schema['properties']['replacement']['properties']);
        $this->assertArrayHasKey('subtitle', $schema['properties']['replacement']['properties']);
        $this->assertArrayHasKey('description', $schema['properties']['replacement']['properties']);
    }

    public function testForModuleType()
    {
        $schema = PatchSchemas::for('module');
        $this->assertEquals('Patch of a module', $schema['title']);
        $this->assertArrayHasKey('replacement', $schema['properties']);
        $this->assertArrayHasKey('title', $schema['properties']['replacement']['properties']);
        $this->assertArrayHasKey('summary', $schema['properties']['replacement']['properties']);
    }

    public function testForLessonType()
    {
        $schema = PatchSchemas::for('lesson');
        $this->assertEquals('Patch of a lesson', $schema['title']);
        $this->assertArrayHasKey('replacement', $schema['properties']);
        $this->assertArrayHasKey('title', $schema['properties']['replacement']['properties']);
        $this->assertArrayHasKey('summary', $schema['properties']['replacement']['properties']);
        $this->assertArrayHasKey('minutes', $schema['properties']['replacement']['properties']);
        $this->assertArrayHasKey('blocks', $schema['properties']['replacement']['properties']);
    }
}
