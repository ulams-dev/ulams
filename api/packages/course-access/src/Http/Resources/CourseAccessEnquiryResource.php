<?php

namespace EscolaLms\CourseAccess\Http\Resources;

use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="CourseAccessEnquiryResource",
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="created_at",
 *          description="created_at",
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="course",
 *          ref="#/components/schemas/CourseShortResource"
 *      ),
 *      @OA\Property(
 *          property="user",
 *          ref="#/components/schemas/UserShortResource"
 *      ),
 *      @OA\Property(
 *          property="status",
 *          description="status",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="data",
 *          description="status",
 *          type="object"
 *      ),
 * )
 *
 * @mixin CourseAccessEnquiry
 */
class CourseAccessEnquiryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'course' => CourseShortResource::make($this->course),
            'user' => UserShortResource::make($this->user),
            'status' => $this->status,
            'data' => $this->data,
        ];
    }
}
