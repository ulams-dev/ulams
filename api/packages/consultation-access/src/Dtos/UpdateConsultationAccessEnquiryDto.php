<?php

namespace EscolaLms\ConsultationAccess\Dtos;

use EscolaLms\ConsultationAccess\Enum\EnquiryStatusEnum;
use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UpdateConsultationAccessEnquiryDto implements DtoContract, InstantiateFromRequest
{
    protected array $proposedTerms;
    protected ?string $description;
    protected ?string $title;
    protected ?string $relatedType;
    protected ?int $relatedId;

    public function __construct(array $proposedTerms, ?string $description, ?string $title, ?string $relatedType, ?int $relatedId)
    {
        $this->proposedTerms = $proposedTerms;
        $this->description = $description;
        $this->title = $title;
        $this->relatedType = $relatedType;
        $this->relatedId = $relatedId;
    }

    public function getProposedTerms(): array
    {
        return collect($this->proposedTerms)
            ->map(fn($term) => Carbon::make($term))
            ->toArray();
    }

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'title' => $this->title,
            'related_type' => $this->relatedType,
            'related_id' => $this->relatedId,
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new self(
            $request->input('proposed_terms'),
            $request->input('description'),
            $request->input('title'),
            $request->input('related_type'),
            $request->input('related_id'),
        );
    }
}
