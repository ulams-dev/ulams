<?php

namespace EscolaLms\ConsultationAccess\Tests\Feature;

use EscolaLms\Auth\Models\User;
use EscolaLms\ConsultationAccess\Enum\EnquiryStatusEnum;
use EscolaLms\ConsultationAccess\Enum\MeetingLinkTypeEnum;
use EscolaLms\ConsultationAccess\Events\ConsultationAccessEnquiryApprovedEvent;
use EscolaLms\ConsultationAccess\Jobs\CreatePencilSpaceJob;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Tests\TestCase;
use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\PencilSpaces\Facades\PencilSpace;
use Illuminate\Support\Facades\Event;

class CreatePencilSpaceJobTest extends TestCase
{
    use CreatesUsers;

    public function testCreatePencilSpaceWithNonExistentEnquiry(): void
    {
        Event::fake([ConsultationAccessEnquiryApprovedEvent::class]);
        CreatePencilSpaceJob::dispatch($this->faker->numberBetween(1));
        Event::assertNothingDispatched();
    }

    public function testCreatePencilSpace(): void
    {
        PencilSpace::fake();
        Event::fake([ConsultationAccessEnquiryApprovedEvent::class]);

        /** @var ConsultationUserPivot $consultationUser */
        $consultationUser = ConsultationUserPivot::factory()
            ->state([
                'user_id' =>  User::factory(),
                'consultation_id' => Consultation::factory(),
            ])
            ->create();

        $userTerm = $consultationUser->userTerms()->create([
            'executed_at' => now()->modify('+2 hours')->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED,
        ]);

        /** @var ConsultationAccessEnquiry $enquiry */
        $enquiry = ConsultationAccessEnquiry::factory()
            ->state([
                'status' => EnquiryStatusEnum::APPROVED,
                'meeting_link' => null,
                'meeting_link_type' => null,
                'consultation_user_id' => $consultationUser->getKey(),
                'consultation_user_term_id' => $userTerm->getKey(),
            ])
            ->create();

        CreatePencilSpaceJob::dispatch($enquiry->getKey());
        $enquiry->refresh();
        $this->assertNotNull($enquiry->meeting_link);
        $this->assertEquals(MeetingLinkTypeEnum::PENCIL_SPACES, $enquiry->meeting_link_type);

        Event::assertDispatched(ConsultationAccessEnquiryApprovedEvent::class);
    }

    public function testCreatePencilSpaceWithNonExistentUser(): void
    {
        Event::fake([ConsultationAccessEnquiryApprovedEvent::class]);

        $user = $this->makeStudent();

        $enquiry = ConsultationAccessEnquiry::factory()
            ->state([
                'user_id' => $user->getKey(),
            ])
            ->create();

        $user->delete();
        CreatePencilSpaceJob::dispatch($enquiry->getKey());
        Event::assertNothingDispatched();
    }
}
