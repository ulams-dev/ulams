<?php

namespace EscolaLms\Tasks\Http\Resources;

use EscolaLms\Tasks\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema(
 *      schema="UserResource",
 *      required={"id", "first_name", "last_name"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="first_name",
 *          description="first_name",
 *          type="string"
 *      ),
 *     @OA\Property(
 *          property="last_name",
 *          description="last_name",
 *          type="string"
 *      ),
 * )
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
        ];
    }
}
