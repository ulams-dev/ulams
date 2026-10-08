<?php

namespace EscolaLms\ConsultationAccess\Dtos;

use EscolaLms\ConsultationAccess\Enum\EnquiryStatusEnum;
use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class ConsultationAccessEnquiryDto implements DtoContract, InstantiateFromRequest
{

    protected int $consultationId;
    protected int $userId;
    protected string $status;
    protected array $proposedTerms;
    protected ?string $description;
    protected ?string $title;
    protected ?string $relatedType;
    protected ?int $relatedId;

    public function __construct(int $consultationId, int $userId, string $status, array $proposedTerms, ?string $description, ?string $title, ?string $relatedType, ?int $relatedId)
    {
        $this->consultationId = $consultationId;
        $this->userId = $userId;
        $this->status = $status;
        $this->proposedTerms = $proposedTerms;
        $this->description = $description;
        $this->title = $title;
        $this->relatedType = $relatedType;
        $this->relatedId = $relatedId;
    }

    public function getConsultationId(): int
    {
        return $this->consultationId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getProposedTerms(): array
    {
        return $this->proposedTerms;
    }

    public function toArray(): array
    {
        return [
            'consultation_id' => $this->getConsultationId(),
            'user_id' => $this->getUserId(),
            'status' => $this->status,
            'description' => $this->description,
            'title' => $this->title,
            'related_type' => $this->relatedType,
            'related_id' => $this->relatedId,
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new self(
            $request->input('consultation_id'),
            auth()->id(),
            EnquiryStatusEnum::PENDING,
            $request->input('proposed_terms'),
            $request->input('description'),
            $request->input('title'),
            $request->input('related_type'),
            $request->input('related_id'),
        );
    }
}
