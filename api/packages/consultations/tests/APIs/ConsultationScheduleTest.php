<?php

namespace EscolaLms\Consultations\Tests\APIs;

use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Enum\ConsultationTermReminderStatusEnum;
use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Events\ReminderAboutTerm;
use EscolaLms\Consultations\Events\ReminderTrainerAboutTerm;
use EscolaLms\Consultations\Jobs\ReminderAboutConsultationJob;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Models\ConsultationProposedTerm;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Tests\Models\User;
use EscolaLms\Consultations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;

class ConsultationScheduleTest extends TestCase
{
    use DatabaseTransactions;
    use WithFaker;
    private Consultation $consultation;
    private ConsultationUserPivot $consultationUserPivot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationsPermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
    }

    private function initVariable(): void
    {
        $this->consultationTerms = collect();
        $this->consultation = Consultation::factory()->create();
        $this->consultation->proposedTerms()->saveMany(ConsultationProposedTerm::factory(3)->create());
    }

    public function testFailReminderAboutConsultationWhenStatusOtherApproved()
    {
        Event::fake();
        $this->initVariable();
        $this->consultationUserPivot = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $this->user->getKey(),
        ])->create();

        $userTerm = $this->consultationUserPivot->userTerms()->create([
            'executed_at' => now()->modify(
                config(
                    'escolalms_consultations.modifier_date.' .
                    ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE, '+1 hour')
            )->format('Y-m-d H:i:s'),
            'executed_status' => $this->faker->randomElement([
                ConsultationTermStatusEnum::REJECT,
                ConsultationTermStatusEnum::REPORTED,
                ConsultationTermStatusEnum::NOT_REPORTED
            ])
        ]);
        $this->assertTrue($this->consultationUserPivot->reminder_status === null);
        $job = new ReminderAboutConsultationJob(ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job->handle();
        Event::assertNotDispatched(ReminderAboutTerm::class);
        Event::assertNotDispatched(ReminderTrainerAboutTerm::class);
        $this->consultationUserPivot->refresh();
        $this->assertTrue(
            $userTerm->reminder_status === null
        );
    }

    public function testReminderAboutConsultationBeforeHour()
    {
        $this->initVariable();
        $this->consultationUserPivot = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $this->user->getKey(),
        ])->create();
        $consultationUserTerm = $this->consultationUserPivot->userTerms()->create([
            'executed_at' => now()->modify(
                config('escolalms_consultations.modifier_date.' .
                    ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE, '+1 hour')
            )->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED
        ]);
        $this->assertTrue($consultationUserTerm->reminder_status === null);
        $job = new ReminderAboutConsultationJob(ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job->handle();
        $consultationUserTerm->refresh();
        $this->assertTrue(
            $consultationUserTerm->reminder_status === ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE
        );
    }

    public function testReminderAboutConsultationBeforeDay()
    {
        $this->initVariable();
        $this->consultationUserPivot = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $this->user->getKey(),
        ])->create();

        $consultationUserTerm = $this->consultationUserPivot->userTerms()->create([
            'executed_at' => now()->modify(config('escolalms_consultations.modifier_date.' .
                ConsultationTermReminderStatusEnum::REMINDED_DAY_BEFORE, '+1 day'))->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED
        ]);

        $this->assertTrue($consultationUserTerm->reminder_status === null);
        $job = new ReminderAboutConsultationJob(ConsultationTermReminderStatusEnum::REMINDED_DAY_BEFORE);
        $job->handle();
        $consultationUserTerm->refresh();
        $this->assertTrue(
            $consultationUserTerm->reminder_status === ConsultationTermReminderStatusEnum::REMINDED_DAY_BEFORE
        );
    }

    public function testReminderAboutConsultationBeforeHourWhenConsultationTermReminderStatusDaily()
    {
        $this->initVariable();
        $this->consultationUserPivot = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $this->user->getKey(),
        ])->create();

        $consultationUserTerm = $this->consultationUserPivot->userTerms()->create([
            'executed_at' => now()->modify(
                config('escolalms_consultations.modifier_date.' .
                    ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE, '+1 hour')
            )->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED,
            'reminder_status' => ConsultationTermReminderStatusEnum::REMINDED_DAY_BEFORE,
        ]);

        $this->assertTrue($consultationUserTerm->reminder_status === ConsultationTermReminderStatusEnum::REMINDED_DAY_BEFORE);
        $job = new ReminderAboutConsultationJob(ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job->handle();
        $consultationUserTerm->refresh();
        $this->assertTrue(
            $consultationUserTerm->reminder_status === ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE
        );
    }

    public function testFailReminderAboutConsultationBeforeHourWhenConsultationTermReminderStatusHour()
    {
        Event::fake();
        $this->initVariable();
        $this->consultationUserPivot = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $this->user->getKey(),
        ])->create();

        $consultationUserTerm = $this->consultationUserPivot->userTerms()->create([
            'executed_at' => now()->modify(
                config('escolalms_consultations.modifier_date.' .
                    ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE, '+1 hour')
            )->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED,
            'reminder_status' => ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE,
        ]);

        $this->assertTrue($consultationUserTerm->reminder_status === ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job = new ReminderAboutConsultationJob(ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE);
        $job->handle();
        $consultationUserTerm->refresh();
        Event::assertNotDispatched(ReminderAboutTerm::class);
        Event::assertNotDispatched(ReminderTrainerAboutTerm::class);
        $this->assertTrue(
            $consultationUserTerm->reminder_status === ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE
        );
    }

    public function testFailReminderAboutConsultationBeforeDayWhenLessThanDay()
    {
        Event::fake();
        $this->initVariable();
        $this->consultationUserPivot = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $this->user->getKey(),

        ])->create();

        $consultationUserTerm = $this->consultationUserPivot->userTerms()->create([
            'executed_at' => now()->modify(
                config('escolalms_consultations.modifier_date.' .
                    ConsultationTermReminderStatusEnum::REMINDED_DAY_BEFORE, '+1 day')
            )->subHour()->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED,
        ]);

        $this->assertTrue($consultationUserTerm->reminder_status === null);
        $job = new ReminderAboutConsultationJob(ConsultationTermReminderStatusEnum::REMINDED_DAY_BEFORE);
        $job->handle();
        $consultationUserTerm->refresh();
        Event::assertNotDispatched(ReminderAboutTerm::class);
        Event::assertNotDispatched(ReminderTrainerAboutTerm::class);
        $this->assertTrue(
            $consultationUserTerm->reminder_status === null
        );
    }
}
