<?php

namespace EscolaLms\ConsultationAccess\Http\Resources;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="ConsultationAccessEnquiryProposedTermsResource",
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="proposed_at",
 *          description="proposed_at",
 *          type="string",
 *          format="date-time"
 *      ),
 * )
 *
 * @mixin ConsultationAccessEnquiryProposedTerm
 */
class ConsultationAccessEnquiryProposedTermsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'proposed_at' => $this->proposed_at,
        ];
    }
}
