<?php

namespace Ulams\Compliance\Tests\Api;

use Illuminate\Support\Facades\Notification;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\User;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder;
use Ulams\TemplatesPdf\Models\FabricPDF;

class CourseSettingsApiTest extends TestCase
{
    use CreatesUsers;

    public function testDefaultsAreShownForACourseWithoutSettings(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->makeAdmin(), 'api')->getJson("/api/admin/compliance/courses/{$course->getKey()}/settings")
            ->assertOk()
            ->assertJsonPath('data.mandatory', false)
            ->assertJsonPath('data.validity_days', null)
            ->assertJsonPath('data.reminder_days', [30, 7, 1])
            ->assertJsonPath('data.escalation_days', [1, 7]);
    }

    public function testSettingsAreSavedNormalisedAndGuarded(): void
    {
        $course = Course::factory()->create();
        $url = "/api/admin/compliance/courses/{$course->getKey()}/settings";
        $admin = $this->makeAdmin();

        $this->actingAs($this->makeStudent(), 'api')->putJson($url, ['mandatory' => true])->assertForbidden();
        $this->actingAs($admin, 'api')->putJson($url, ['validity_days' => 0])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson($url, ['reminder_days' => ['x']])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson('/api/admin/compliance/courses/999999/settings', ['mandatory' => true])->assertNotFound();

        $this->actingAs($admin, 'api')->putJson($url, ['mandatory' => true, 'validity_days' => 365, 'reminder_days' => [7, 30, 7], 'escalation_days' => [7, 3]])
            ->assertOk()
            ->assertJsonPath('data.mandatory', true)
            ->assertJsonPath('data.reminder_days', [30, 7])
            ->assertJsonPath('data.escalation_days', [3, 7]);
        $this->actingAs($admin, 'api')->getJson($url)->assertJsonPath('data.validity_days', 365);
    }

    public function testNewCertificatesExpireAfterTheCourseValidity(): void
    {
        Notification::fake();
        $this->travelTo(now()->setTime(12, 0)); // the expiry date must not depend on crossing midnight
        $this->seed(TemplatesPdfSeeder::class);
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $other = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $this->actingAs($this->makeAdmin(), 'api')->putJson("/api/admin/compliance/courses/{$course->getKey()}/settings", ['validity_days' => 90])->assertOk();
        $student = User::factory()->create();

        app(TemplateEventListener::class)->handle(new CourseFinished(CoreUser::find($student->getKey()), $course));
        app(TemplateEventListener::class)->handle(new CourseFinished(CoreUser::find($student->getKey()), $other));

        $with = FabricPDF::where('assignable_id', $course->getKey())->firstOrFail();
        $without = FabricPDF::where('assignable_id', $other->getKey())->firstOrFail();
        $this->assertSame(now()->addDays(90)->format('Y-m-d'), $with->expires_at->format('Y-m-d'));
        $this->assertNull($without->expires_at);
    }
}
