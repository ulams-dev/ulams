<?php

namespace Tests\Integrations;

use Database\Seeders\Demo\GravityExperience;
use Database\Seeders\Demo\PolandExperience;
use Database\Seeders\Demo\PolandPolishExperience;
use Database\Seeders\Demo\Support\ModuleFile;
use Database\Seeders\Demo\Support\Sources;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;
use Ulams\TopicTypeGift\Enum\QuestionTypeEnum;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use Ulams\TopicTypeLayout\Services\LayoutDocumentValidator;

/**
 * The course text of the gravity and poland academies (Demo/content/<key>/modules/*.md): every module
 * parses, every layout validates against the learner catalogue (no fallback is ever shown), every GIFT
 * question parses as a real question type, and every `{{src:ID}}` resolves. The step ranges against the
 * packages are checked by demo-content/tests/unit/courses.test.mjs, the seeding itself by the demo reset.
 */
class DemoInteractiveContentTest extends TestCase
{
    public static function courses(): array
    {
        return [
            'gravity' => [GravityExperience::class, 'en', 15],
            'poland (English)' => [PolandExperience::class, 'en', 12],
            'poland (Polish)' => [PolandPolishExperience::class, 'pl', 12],
        ];
    }

    #[DataProvider('courses')]
    public function testEveryModuleIsReadyToSeed(string $class, string $language, int $finalQuestions): void
    {
        $experience = new $class();
        $files = $experience->moduleFiles();
        $this->assertCount(12, $files, 'a welcome lesson, nine modules, the final test and the sources');

        $validator = new LayoutDocumentValidator();
        $gift = app(GiftQuestionServiceContract::class);
        $sources = $experience->sources();
        $counts = ['interactive' => 0, 'richtext' => 0, 'layout' => 0, 'quiz' => 0];
        $questions = [];

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

                if ($block['kind'] === 'layout') {
                    $layout = json_decode($block['body'], true);
                    $this->assertIsArray($layout, "$name: $title is JSON");
                    $this->assertSame([], $validator->validate($layout['document'], 10), "$name: $title is a valid layout");
                    $this->assertNotSame('', trim((string) $layout['fallback']), "$name: $title has a fallback");
                    $this->assertNotSame('', $block['attrs']['sources'] ?? '', "$name: $title lists its sources");
                    $names = $sources->names(array_map('trim', explode(',', $block['attrs']['sources'])));
                    $this->assertNotSame('', $names);
                }
                if ($block['kind'] === 'quiz') {
                    $list = ModuleFile::giftQuestions($block['body']);
                    $questions[$name] = count($list);
                    foreach ($list as $question) {
                        $type = $gift->getType($question);
                        $this->assertNotContains($type, [QuestionTypeEnum::DESCRIPTION, QuestionTypeEnum::ESSAY, QuestionTypeEnum::SHORT_ANSWERS], "$name: " . substr($question, 0, 50));
                    }
                }
            }
        }

        $this->assertGreaterThanOrEqual(18, $counts['interactive']);
        $this->assertSame(9, $counts['layout'], 'one Layout topic per module');
        $this->assertSame(10, $counts['quiz'], 'nine module quizzes and the final test');
        $this->assertSame($finalQuestions, $questions['10-final-test.md']);
        foreach (array_slice($questions, 0, 9) as $file => $count) {
            $this->assertGreaterThanOrEqual(3, $count, $file);
            $this->assertLessThanOrEqual(6, $count, $file);
        }
    }

    #[DataProvider('courses')]
    public function testTheCourseIsFreeAndInItsLanguage(string $class, string $language, int $finalQuestions): void
    {
        $fields = (new ReflectionMethod($class, 'courseFields'))->invoke(new $class());

        $this->assertSame($language, $fields['language']);
        $this->assertTrue($fields['public']);
        $this->assertNull($fields['hours_to_complete']);
        $this->assertNotEmpty($fields['description']);
        $this->assertStringContainsString('CC BY 4.0', implode(' ', $fields['fields']['landing']['lists']['sources']), 'the landing Sources section names the licence');
    }
}
