<?php

namespace Ulams\ConsultationAccess\Tests\Feature;

use Carbon\Carbon;
use Ulams\Auth\Models\User;
use Ulams\ConsultationAccess\Enum\EnquiryStatusEnum;
use Ulams\ConsultationAccess\Jobs\Strategies\SpaceTitleStrategyFactory;
use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Ulams\ConsultationAccess\Tests\TestCase;
use Ulams\Consultations\Models\Consultation;
use Ulams\Consultations\Models\ConsultationUserPivot;
use Ulams\Consultations\Models\ConsultationUserTerm;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;

class SpaceTitleStrategyTest extends TestCase
{
    private ConsultationAccessEnquiry $enquiry;
    private ConsultationUserPivot $consultationUser;
    private ConsultationUserTerm $consultationUserTerm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consultationUser = ConsultationUserPivot::factory()
            ->state([
                'user_id' => User::factory(),
                'consultation_id' => Consultation::factory(),
            ])
            ->create();

        $this->consultationUserTerm = $this->consultationUser->userTerms()->create([
           'executed_at' => now()->format('Y-m-d H:i:s'),
        ]);

        /** @var ConsultationAccessEnquiry $enquiry */
        $this->enquiry = ConsultationAccessEnquiry::factory()
            ->state([
                'status' => EnquiryStatusEnum::APPROVED,
                'meeting_link' => null,
                'meeting_link_type' => null,
                'consultation_user_id' => $this->consultationUser->getKey(),
                'consultation_user_term_id' => $this->consultationUserTerm->getKey(),
            ])
            ->create();

    }

    public function testDefaultSpaceTitle(): void
    {
        $title = SpaceTitleStrategyFactory::create($this->enquiry)->getTitle();
        $this->assertEquals($this->getDefaultTitle(), $title);
    }

    public function testSpaceTitleWhenTopicIsRelated(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();
        $topic = Topic::factory()->state(['lesson_id' => $lesson->getKey()])->create();

        $this->enquiry->update([
            'related_type' => Topic::class,
            'related_id' => $topic->getKey(),
        ]);
        $title = SpaceTitleStrategyFactory::create($this->enquiry)->getTitle();
        $this->assertEquals($course->title . ' ' . $this->getDefaultTitle(), $title);
    }

    public function testSpaceTitleWhenLessonIsRelated(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();

        $this->enquiry->update([
            'related_type' => Lesson::class,
            'related_id' => $lesson->getKey(),
        ]);
        $title = SpaceTitleStrategyFactory::create($this->enquiry)->getTitle();
        $this->assertEquals($course->title . ' ' . $this->getDefaultTitle(), $title);
    }

    public function testSpaceTitleWhenCourseIsRelated(): void
    {
        $course = Course::factory()->create();

        $this->enquiry->update([
            'related_type' => Course::class,
            'related_id' => $course->getKey(),
        ]);
        $title = SpaceTitleStrategyFactory::create($this->enquiry)->getTitle();
        $this->assertEquals($course->title . ' ' . $this->getDefaultTitle(), $title);
    }

    public function testSpaceTitleWhenRelatedModelNotExist(): void
    {
        $course = Course::factory()->create();

        $this->enquiry->update([
            'related_type' => Course::class,
            'related_id' => $course->getKey(),
        ]);

        $course->delete();
        $title = SpaceTitleStrategyFactory::create($this->enquiry)->getTitle();
        $this->assertEquals($this->getDefaultTitle(), $title);
    }

    private function getDefaultTitle(): string
    {
        return sprintf(
            '%s (%d) %s',
            $this->enquiry->user->name,
            $this->enquiry->user->getKey(),
            Carbon::make($this->enquiry->consultationUserTerm->executed_at)->format('d-m-Y')
        );
    }
}
