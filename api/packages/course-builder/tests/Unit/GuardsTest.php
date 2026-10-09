<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\CourseBuilder\Ui\UiCatalogue;

/**
 * Architecture guards: LMS entities are changed only through domain services (no direct table
 * writes from this package), and every component the server streams exists in the catalogue
 * manifest exported by @ulams/ui.
 */
class GuardsTest extends TestCase
{
    private function sources(): array
    {
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src', RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() === 'php') {
                $files[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }

    public function testNoDirectWritesToLmsTables(): void
    {
        $offenders = [];
        foreach ($this->sources() as $path => $code) {
            if (preg_match('/DB::(table|insert|update|delete|statement|unprepared)\s*\(|DB::connection\([^)]*\)->table\s*\(/', $code)) {
                $offenders[] = basename($path) . ': query builder write';
            }
            // LMS models may be read (find/query), never written from this package
            if (preg_match('/\\\\?Ulams\\\\(Courses|TopicTypes|TopicTypeGift|Pages)\\\\Models\\\\[A-Za-z\\\\]+::(create|insert|forceCreate|updateOrCreate|firstOrCreate|destroy)\s*\(/', $code)
                || preg_match('/\\\\?Ulams\\\\(Courses|TopicTypes|TopicTypeGift|Pages)\\\\Models\\\\[A-Za-z\\\\]+::query\(\)[^;]*->(update|delete|insert|forceDelete)\s*\(/', $code)) {
                $offenders[] = basename($path) . ': Eloquent write on an LMS model';
            }
        }

        $this->assertSame([], $offenders);
    }

    public function testSurfaceComponentsExistInTheCatalogue(): void
    {
        $catalogue = app(UiCatalogue::class);
        foreach ($this->sources() as $path => $code) {
            preg_match_all("/'component' => '([A-Z][A-Za-z]+)'/", $code, $m);
            foreach (array_unique($m[1]) as $component) {
                if (in_array($component, ['Page', 'SiteHeader', 'Main', 'SiteFooter', 'Hero', 'FeatureList', 'Syllabus', 'Faq', 'CourseHeader'], true)) {
                    continue; // landing documents use the page catalogue (registry.ts)
                }
                $this->assertTrue($catalogue->has($component), basename($path) . " streams unknown component {$component}");
            }
        }
        $this->assertSame(['ChoiceChips', 'SingleChoice', 'DurationSlider', 'LanguagePicker'], $catalogue->selectable('interview'));
        $this->assertSame(['LessonPreviewCard', 'QuizQuestionCard', 'DiffView'], $catalogue->selectable('chat-reply'));
    }

    public function testInvalidModelChoicesFallBackToText(): void
    {
        $catalogue = app(UiCatalogue::class);
        $this->assertSame('Text', $catalogue->nodeOrFallback('a', 'Marquee', ['x' => 1], 'fallback')['component']);
        $this->assertSame('Text', $catalogue->nodeOrFallback('a', 'SingleChoice', ['questionKey' => 'level'], 'fallback')['component']);
        $this->assertSame('Text', $catalogue->nodeOrFallback('a', 'OutlineDiff', [], 'fallback', 'interview')['component']);
        $ok = $catalogue->nodeOrFallback('a', 'SingleChoice', ['questionKey' => 'level', 'label' => 'Level?', 'status' => 'open', 'defaultValue' => 'beginner',
            'options' => [['value' => 'beginner', 'label' => 'Beginner'], ['value' => 'advanced', 'label' => 'Advanced']]], 'fallback', 'interview');
        $this->assertSame('SingleChoice', $ok['component']);
        $this->assertSame('a', $ok['id']);
    }
}
