<?php

namespace EscolaLms\ConsultationAccess\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class ApproveConsultationAccessEnquiryDto implements DtoContract, InstantiateFromRequest
{
    private int $proposedTermId;
    private ?string $meetingLink;

    public function __construct(int $proposedTermId, ?string $meetingLink)
    {
        $this->proposedTermId = $proposedTermId;
        $this->meetingLink = $meetingLink;
    }

    public function toArray(): array
    {
        return [];
    }

    public function getProposedTermId(): int
    {
        return $this->proposedTermId;
    }

    public function getMeetingLink(): ?string
    {
        return $this->meetingLink;
    }

    public static function instantiateFromRequest(Request $request): ApproveConsultationAccessEnquiryDto
    {
        return new self(
            $request->input('proposed_term_id'),
            $request->input('meeting_link')
        );
    }
}
