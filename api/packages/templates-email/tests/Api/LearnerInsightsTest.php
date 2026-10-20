<?php

namespace Ulams\TemplatesEmail\Tests\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\User;
use Ulams\LearnerInsights\Events\LearnerNudged;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Tests\TestCase;

class LearnerInsightsTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    public function testTheNudgeEmailCarriesTheCourseTheReasonAndTheLink(): void
    {
        if (!class_exists(\Ulams\LearnerInsights\UlamsLearnerInsightsServiceProvider::class)) {
            $this->markTestSkipped('Learner insights package not installed');
        }
        Notification::fake();
        Mail::fake();
        $user = User::factory()->create();

        app(TemplateEventListener::class)->handle(new LearnerNudged($user, [
            'courseId' => 1,
            'courseTitle' => 'Git Basics',
            'reasonText' => '7 days without activity in this course',
            'continueUrl' => 'https://learn.example.com/learn/1/2?n=abc',
            'nudgeId' => 'abc',
        ]));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user) {
            $this->assertEquals(__('Continue ":course"', ['course' => 'Git Basics']), $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            $html = $mailable->render();
            $this->assertStringContainsString('7 days without activity in this course', $html);
            $this->assertStringContainsString('https://learn.example.com/learn/1/2?n=abc', $html);

            return true;
        });
    }
}
