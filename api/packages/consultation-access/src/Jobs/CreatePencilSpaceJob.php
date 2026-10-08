<?php

namespace Ulams\ConsultationAccess\Jobs;

use Ulams\ConsultationAccess\Enum\MeetingLinkTypeEnum;
use Ulams\ConsultationAccess\Events\ConsultationAccessEnquiryApprovedEvent;
use Ulams\ConsultationAccess\Jobs\Strategies\SpaceTitleStrategyFactory;
use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Ulams\ConsultationAccess\Repositories\Contracts\ConsultationAccessEnquiryRepositoryContract;
use Ulams\PencilSpaces\Facades\PencilSpace;
use Ulams\PencilSpaces\Resource\CreatePencilSpaceResource;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class CreatePencilSpaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $consultationAccessEnquiryId;

    public function __construct(int $consultationAccessEnquiryId)
    {
        $this->consultationAccessEnquiryId = $consultationAccessEnquiryId;
    }

    public function handle(ConsultationAccessEnquiryRepositoryContract $consultationAccessEnquiryRepository): void
    {
        try {
            /** @var ?ConsultationAccessEnquiry $enquiry */
            $enquiry = $consultationAccessEnquiryRepository->find($this->consultationAccessEnquiryId);

            if (!$enquiry) {
                return;
            }

            $resource = new CreatePencilSpaceResource(
                SpaceTitleStrategyFactory::create($enquiry)->getTitle(),
                collect($enquiry->consultation->author_id),
                collect($enquiry->user_id)
            );

            $space = PencilSpace::createSpace($resource);

            $consultationAccessEnquiryRepository->update([
                'meeting_link' => Arr::get($space, 'link'),
                'meeting_link_type' => MeetingLinkTypeEnum::PENCIL_SPACES,
            ], $this->consultationAccessEnquiryId);

            event(new ConsultationAccessEnquiryApprovedEvent($enquiry->user, $enquiry));
        } catch (Exception $e) {
            Log::error('[ConsultationAccess][CreatePencilSpaceJob] Fails', ['error' => $e->getMessage()]);
            $this->fail();
        }
    }
}
