<?php

namespace Ulams\Compliance\Tests\Feature;

use Ulams\Compliance\Services\CourseSettings;
use Ulams\Compliance\Services\PathEnrollmentService;
use Ulams\Compliance\Services\PathService;
use Ulams\Compliance\Tests\NotificationsTestCase;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;

/**
 * The step e-mails use the per-language templates (language of the course, then the tenant default, then English)
 * and the path sentence is written in the same language.
 */
class PathNotificationLanguageTest extends NotificationsTestCase
{
    use CreatesUsers;

    protected array $templateLocales = ['en', 'pl'];

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        // the seeder writes the Polish default templates through __(): the app's own pl.json must be loaded before it runs
        $app['translator']->addJsonPath(__DIR__ . '/../../../../resources/lang');
    }

    private function enrolOneCourse(?string $language): array
    {
        $course = Course::factory()->create(['title' => 'Kurs', 'language' => $language, 'status' => CourseStatusEnum::PUBLISHED]);
        app(CourseSettings::class)->save($course->getKey(), ['mandatory' => true]);
        $learner = CoreUser::find($this->makeStudent(['email' => 'ada@example.test'])->getKey());
        $path = app(PathService::class)->save(['title' => 'Wdrożenie', 'steps' => [['course_id' => $course->getKey()]]]);
        app(PathEnrollmentService::class)->enroll($learner, $path);

        return [$learner, $path];
    }

    public function testACourseInPolishGetsThePolishTemplateAndSentence(): void
    {
        $this->enrolOneCourse('pl');

        $subjects = array_column($this->sentMails(), 1);
        $this->assertContains('Obowiązkowe szkolenie: Kurs', $subjects);
        // the path itself has no language: its notices follow the academy's default language (English here)
        $this->assertContains('Learning path: Wdrożenie', $subjects);
        $this->assertStringContainsString('Krok 1 z 1 ścieżki „Wdrożenie” jest już otwarty.', $this->mailBody('ada@example.test', 'Obowiązkowe szkolenie: Kurs'));
        $this->assertSame('en', app()->getLocale()); // the locale is restored after the sentence is built
    }

    public function testACourseInEnglishAndOneWithoutALanguageGetTheEnglishTemplate(): void
    {
        $this->enrolOneCourse('en');
        $this->assertContains('Required training: Kurs', array_column($this->sentMails(), 1));
        $this->assertStringContainsString('Step 1 of 1 of the learning path &quot;Wdrożenie&quot; is now open.', $this->mailBody('ada@example.test', 'Required training: Kurs'));
    }

    public function testACourseWithoutALanguageFallsBackToTheTenantDefaultThenEnglish(): void
    {
        $this->enrolOneCourse(null);
        $this->assertContains('Required training: Kurs', array_column($this->sentMails(), 1));
    }
}
