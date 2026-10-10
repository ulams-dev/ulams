<?php

namespace Tests\Integrations;

use Database\Seeders\Demo\Support\AssetFactory;
use Database\Seeders\Demo\Support\ContentPackages;
use Database\Seeders\Demo\Support\ModuleFile;
use Database\Seeders\Demo\UlamExperience;
use ReflectionMethod;
use Tests\TestCase;
use Ulams\TopicTypeGift\Enum\QuestionTypeEnum;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use Ulams\TopicTypeLayout\Services\LayoutDocumentValidator;

/**
 * The text of the Ulam course (Demo/content/ulam/modules/*.md): eleven files (a welcome lesson, eight modules, the final
 * test and the sources lesson), every layout validates against the learner catalogue (no fallback is ever shown), every
 * GIFT question parses as a real question type, every `{{src:ID}}` resolves, every `{{asset:file}}` exists, every
 * interactive topic names one of the five packages and a step range of it, and the course is free, public and in English.
 * The step ranges against the manifests, the facts behind every quiz question and the quotations are checked by
 * demo-content/tests/unit/ulam-course.test.mjs; the seeding itself by the demo reset.
 */
class DemoUlamContentTest extends TestCase
{
    public function testEveryModuleIsReadyToSeed(): void
    {
        $experience = new UlamExperience();
        $files = $experience->moduleFiles();
        $this->assertCount(11, $files, 'a welcome lesson, eight modules, the final test and the sources');

        $validator = new LayoutDocumentValidator();
        $gift = app(GiftQuestionServiceContract::class);
        $sources = $experience->sources();
        $counts = ['interactive' => 0, 'richtext' => 0, 'layout' => 0, 'quiz' => 0];
        $questions = [];
        $types = [];
        $packages = [];

        foreach ($files as $file) {
            $name = basename($file);
            $module = ModuleFile::parse((string) file_get_contents($file), $name);
            $this->assertNotEmpty($module['meta']['title'] ?? '', "$name has a title");
            $this->assertNotEmpty($module['blocks'], "$name has blocks");

            foreach ($module['blocks'] as $block) {
                $counts[$block['kind']]++;
                $title = $block['attrs']['title'] ?? '';
                $this->assertNotSame('', $title, "$name: a {$block['kind']} block has a title");
                $sources->cited($block['body']); // throws for an unknown id
                foreach (array_filter(array_map('trim', explode(',', $block['attrs']['sources'] ?? ''))) as $id) {
                    $this->assertTrue($sources->has($id), "$name: $title cites unknown source $id");
                }
                preg_match_all('/\{\{asset:([\w.-]+)\}\}/', $block['body'], $assets);
                foreach ($assets[1] as $asset) {
                    $this->assertFileExists(AssetFactory::assetsPath('ulam/images/' . $asset), "$name: $title uses the image $asset");
                }

                if ($block['kind'] === 'interactive') {
                    $package = $block['attrs']['package'] ?? '';
                    $this->assertContains($package, ContentPackages::ULAM, "$name: $title names one of the five packages");
                    $packages[$package] = true;
                    $this->assertSame('inline', $block['attrs']['display'] ?? null, "$name: $title is played inline");
                }
                if ($block['kind'] === 'layout') {
                    $layout = json_decode($block['body'], true);
                    $this->assertIsArray($layout, "$name: $title is JSON");
                    $this->assertSame([], $validator->validate($layout['document'], 10), "$name: $title is a valid layout");
                    $this->assertNotSame('', trim((string) $layout['fallback']), "$name: $title has a fallback");
                    $this->assertNotSame('', $block['attrs']['sources'] ?? '', "$name: $title lists its sources");
                    $this->assertNotSame('', $sources->names(array_map('trim', explode(',', $block['attrs']['sources']))));
                }
                if ($block['kind'] === 'quiz') {
                    $list = ModuleFile::giftQuestions($block['body']);
                    $questions[$name] = count($list);
                    foreach ($list as $question) {
                        $type = $gift->getType($question);
                        $this->assertNotContains($type, [QuestionTypeEnum::DESCRIPTION, QuestionTypeEnum::ESSAY, QuestionTypeEnum::SHORT_ANSWERS], "$name: " . substr($question, 0, 50));
                        $types[$type] = true;
                    }
                }
            }
        }

        $this->assertSame(12, $counts['interactive']);
        $this->assertSame(8, $counts['layout'], 'one Layout topic per module');
        $this->assertSame(9, $counts['quiz'], 'eight module quizzes and the final test');
        $this->assertEqualsCanonicalizing(ContentPackages::ULAM, array_keys($packages), 'every package is used');
        $this->assertSame(14, $questions['09-final-test.md']);
        $module = array_values(array_filter($questions, fn (int $n, string $f) => !str_starts_with($f, '09'), ARRAY_FILTER_USE_BOTH));
        $this->assertSame([5, 7, 5, 5, 3, 5, 4, 4], $module);
        foreach ([QuestionTypeEnum::MULTIPLE_CHOICE, QuestionTypeEnum::NUMERICAL_QUESTION, QuestionTypeEnum::MATCHING, QuestionTypeEnum::TRUE_FALSE] as $type) {
            $this->assertArrayHasKey($type, $types, "no $type question");
        }
    }

    public function testTheCourseIsFreePublicAndInEnglish(): void
    {
        $fields = (new ReflectionMethod(UlamExperience::class, 'courseFields'))->invoke(new UlamExperience());

        $this->assertSame('en', $fields['language']);
        $this->assertTrue($fields['public']);
        $this->assertNull($fields['hours_to_complete']);
        $this->assertNotEmpty($fields['description']);
        $this->assertStringNotContainsStringIgnoringCase('placeholder', $fields['description']);
        $this->assertStringContainsString('CC BY 4.0', implode(' ', $fields['fields']['landing']['lists']['sources']), 'the landing Sources section names the licence');
        $faq = implode(' ', array_column($fields['fields']['landing']['faq'], 'a'));
        $this->assertStringContainsString('not affiliated with or endorsed by his estate', $faq, 'the landing carries the product-name sentence');
    }

    public function testTheLandingHeroPlaysTheSpiral(): void
    {
        // the showcase endpoint returns the first Interactive topic of the first lesson: the welcome lesson ends with the spiral
        $first = ModuleFile::parse((string) file_get_contents((new UlamExperience())->moduleFiles()[0]), '00-welcome.md');
        $interactive = array_values(array_filter($first['blocks'], fn (array $b) => $b['kind'] === 'interactive'));
        $this->assertCount(1, $interactive);
        $this->assertSame('ulam-spiral', $interactive[0]['attrs']['package']);
        $this->assertSame('richtext', $first['blocks'][0]['kind']);
    }

    public function testEveryPackageHasALibraryTitle(): void
    {
        foreach (ContentPackages::ULAM as $name) {
            $this->assertArrayHasKey($name, ContentPackages::TITLES);
        }
        $this->assertCount(5, array_unique(array_intersect_key(ContentPackages::TITLES, array_flip(ContentPackages::ULAM))));
    }
}
