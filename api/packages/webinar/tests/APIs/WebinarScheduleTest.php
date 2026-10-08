<?php

namespace Ulams\Webinar\Tests\APIs;

use Ulams\Webinar\Database\Seeders\WebinarsPermissionSeeder;
use Ulams\Webinar\Enum\WebinarTermReminderStatusEnum;
use Ulams\Webinar\Events\ReminderAboutTerm;
use Ulams\Webinar\Jobs\ReminderAboutWebinarJob;
use Ulams\Webinar\Models\Webinar;
use Ulams\Webinar\Tests\Models\User;
use Ulams\Webinar\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;

class WebinarScheduleTest extends TestCase
{
    use DatabaseTransactions;
    use WithFaker;
    private Webinar $webinar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WebinarsPermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
    }

    public function testFailReminderAboutWebinarWhenStatusOtherApproved()
    {
        $this->webinar = Webinar::factory([
            'reminder_status' => WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE
        ])->create();
        $this->user->webinars()->sync([$this->webinar->getKey()]);
        Event::fake();
        $this->assertTrue($this->webinar->reminder_status === WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job = new ReminderAboutWebinarJob(WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job->handle();
        Event::assertNotDispatched(ReminderAboutTerm::class);
        $this->webinar->refresh();
        $this->assertTrue(
            $this->webinar->reminder_status === WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE
        );
    }

    public function testReminderAboutWebinarBeforeHour()
    {
        $this->webinar = Webinar::factory()->create();
        $this->webinar->users()->sync([$this->user->getKey()]);
        $this->assertTrue($this->webinar->reminder_status === null);
        $job = new ReminderAboutWebinarJob(WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job->handle();
        $this->webinar->refresh();
        $this->assertTrue(
            $this->webinar->reminder_status === WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE
        );
    }

    public function testReminderAboutWebinarBeforeDay()
    {
        $this->webinar = Webinar::factory()->create();
        $this->user->webinars()->sync([$this->webinar->getKey()]);
        $this->assertTrue($this->webinar->reminder_status === null);
        $job = new ReminderAboutWebinarJob(WebinarTermReminderStatusEnum::REMINDED_DAY_BEFORE);
        $job->handle();
        $this->webinar->refresh();
        $this->assertTrue(
            $this->webinar->reminder_status === WebinarTermReminderStatusEnum::REMINDED_DAY_BEFORE
        );
    }

    public function testReminderAboutWebinarBeforeHourWhenWebinarReminderStatusDaily()
    {
        $this->webinar = Webinar::factory([
            'reminder_status' => WebinarTermReminderStatusEnum::REMINDED_DAY_BEFORE
        ])->create();
        $this->user->webinars()->sync([$this->webinar->getKey()]);
        $this->assertTrue($this->webinar->reminder_status === WebinarTermReminderStatusEnum::REMINDED_DAY_BEFORE);
        $job = new ReminderAboutWebinarJob(WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job->handle();
        $this->webinar->refresh();
        $this->assertTrue(
            $this->webinar->reminder_status === WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE
        );
    }

    public function testFailReminderAboutWebinarBeforeHourWhenWebinarReminderStatusHour()
    {
        Event::fake();
        $this->webinar = Webinar::factory([
            'reminder_status' => WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE
        ])->create();
        $this->user->webinars()->sync([$this->webinar->getKey()]);
        $this->assertTrue($this->webinar->reminder_status === WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job = new ReminderAboutWebinarJob(WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job->handle();
        $this->webinar->refresh();
        Event::assertNotDispatched(ReminderAboutTerm::class);
        $this->assertTrue(
            $this->webinar->reminder_status === WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE
        );
    }
}
