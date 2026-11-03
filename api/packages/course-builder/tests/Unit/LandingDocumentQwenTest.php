<?php

declare(strict_types=1);

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\Apply\LandingDocument;

class LandingDocumentQwenTest extends TestCase
{
    public function testLandingReturnsCorrectStructure()
    {
        $doc = [
            'course' => [
                'title' => 'Test Course',
                'subtitle' => 'Test Subtitle',
                'description' => 'Test Description',
                'seo' => [
                    'title' => 'SEO Title',
                    'description' => 'SEO Description',
                ],
                'objectives' => [
                    ['text' => 'Objective 1'],
                    ['text' => 'Objective 2'],
                ],
                'faq' => [
                    ['question' => 'Question 1', 'answer' => 'Answer 1'],
                    ['question' => 'Question 2', 'answer' => 'Answer 2'],
                ],
            ],
            'modules' => [
                [
                    'title' => 'Module 1',
                    'summary' => 'Summary 1',
                    'lessons' => [
                        ['title' => 'Lesson 1', 'minutes' => 10],
                        ['title' => 'Lesson 2', 'minutes' => 20],
                    ],
                ],
            ],
        ];

        $brief = [
            'level' => 'Intermediate',
            'audience' => 'Students',
        ];

        $result = LandingDocument::landing($doc, $brief);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('component', $result);
        $this->assertArrayHasKey('props', $result);
        $this->assertArrayHasKey('children', $result);
    }

    public function testHeaderReturnsCorrectStructure()
    {
        $doc = [
            'course' => [
                'title' => 'Test Course',
                'subtitle' => 'Test Subtitle',
                'description' => 'Test Description',
                'language' => 'English',
            ],
        ];

        $brief = [
            'level' => 'Intermediate',
        ];

        $result = LandingDocument::header($doc, $brief);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('component', $result);
        $this->assertArrayHasKey('props', $result);
    }

    public function testCutTruncatesText()
    {
        $text = 'This is a long text that needs to be truncated to fit within the specified maximum length.';
        $max = 20;

        $reflection = new \ReflectionClass(LandingDocument::class);
        $method = $reflection->getMethod('cut');
        $method->setAccessible(true);

        $result = $method->invoke(null, $text, $max);

        $this->assertIsString($result);
        $this->assertStringStartsWith('This is a', $result);
        $this->assertStringEndsWith('…', $result);
        $this->assertLessThanOrEqual($max, mb_strlen($result));
    }

    public function testCutDoesNotTruncateShortText()
    {
        $text = 'Short text';
        $max = 20;

        $reflection = new \ReflectionClass(LandingDocument::class);
        $method = $reflection->getMethod('cut');
        $method->setAccessible(true);

        $result = $method->invoke(null, $text, $max);

        $this->assertIsString($result);
        $this->assertEquals($text, $result);
    }

    public function testCutHandlesEmptyText()
    {
        $text = '';
        $max = 20;

        $reflection = new \ReflectionClass(LandingDocument::class);
        $method = $reflection->getMethod('cut');
        $method->setAccessible(true);

        $result = $method->invoke(null, $text, $max);

        $this->assertIsString($result);
        $this->assertEquals('', $result);
    }

    public function testCutHandlesWhitespaceOnlyText()
    {
        $text = '   ';
        $max = 20;

        $reflection = new \ReflectionClass(LandingDocument::class);
        $method = $reflection->getMethod('cut');
        $method->setAccessible(true);

        $result = $method->invoke(null, $text, $max);

        $this->assertIsString($result);
        $this->assertEquals('', $result);
    }
}
