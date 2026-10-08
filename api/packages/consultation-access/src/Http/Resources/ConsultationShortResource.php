<?php

namespace EscolaLms\ConsultationAccess\Http\Resources;

use EscolaLms\ConsultationAccess\Models\Consultation;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="ConsultationShortResource",
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="name",
 *          description="name",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="author",
 *          description="author",
 *          ref="#/components/schemas/ConsultationUserShortResource"
 *      ),
 * )
 *
 * @mixin Consultation
 */
class ConsultationShortResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'author' => UserShortResource::make($this->resource->author),
        ];
    }
}
