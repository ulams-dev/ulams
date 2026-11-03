<?php

namespace Ulams\CourseVariants\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\AuthServiceProvider as CoursesAuthServiceProvider;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\CourseVariants\Database\Seeders\CourseVariantsPermissionSeeder;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Services\EntitlementService;
use Ulams\Courses\Tests\Models\User;
use Ulams\CourseVariants\UlamsCourseVariantsServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(CourseVariantsPermissionSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            CoursesAuthServiceProvider::class,
            UlamsCourseVariantsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('cache.default', 'array');
    }

    protected function student(): \Illuminate\Contracts\Auth\Authenticatable
    {
        return $this->makeStudent();
    }

    protected function admin(): \Illuminate\Contracts\Auth\Authenticatable
    {
        return $this->makeAdmin();
    }

    protected function publishedCourse(string $title): Course
    {
        return Course::factory()->create([
            'status' => CourseStatusEnum::PUBLISHED, 'active_from' => null, 'active_to' => null,
            'title' => $title, 'subtitle' => '', 'description' => '', 'findable' => true,
        ]);
    }

    /**
     * A family: a default course (intermediate) and a variant course per level given, all published.
     *
     * @param string[] $levels
     *
     * @return array{group:VariantGroup,default:Course,variants:array<string,Course>}
     */
    protected function family(array $levels = ['beginner', 'expert']): array
    {
        $default = $this->publishedCourse('Git basics');
        $service = $this->app->make(CourseVariantServiceContract::class);
        $service->setDefaultAudience($default->getKey(), 'Intermediate', 'intermediate', null);
        $variants = [];
        foreach ($levels as $i => $level) {
            $variants[$level] = $this->publishedCourse('Git basics (' . ucfirst($level) . ')');
            $service->addMember($default->getKey(), $variants[$level]->getKey(), $level, ucfirst($level), $level, null, $i + 1);
        }

        return ['group' => VariantGroup::query()->with('members')->where('default_course_id', $default->getKey())->firstOrFail(), 'default' => $default, 'variants' => $variants];
    }

    /** The learner buys the family's default course: one order entitlement, which is all the family needs. */
    protected function buy($user, Course $course, ?\Carbon\CarbonInterface $until = null, ?string $key = null): Entitlement
    {
        return $this->app->make(EntitlementService::class)->grantCourse($user->getKey(), $course->getKey(), Entitlement::SOURCE_ORDER_ITEM, $key ?? "order:{$course->getKey()}:{$user->getKey()}", [], $until);
    }

    /** A lesson with `$count` active topics in the course. @return Topic[] */
    protected function topics(Course $course, int $count): array
    {
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey(), 'active' => true]);

        return array_map(fn (int $i) => Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true, 'order' => $i]), range(1, $count));
    }
}
