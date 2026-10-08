<?php

namespace EscolaLms\Dictionaries\Http\Resources;

use EscolaLms\Dictionaries\Models\DictionaryUser;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="DictionaryAccessAdminResource",
 *      @OA\Property(
 *          property="end_date",
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="is_active",
 *          type="boolean",
 *       ),
 *      @OA\Property(
 *          property="user",
 *          type="object",
 *       ),
 * )
 *
 * @mixin DictionaryUser
 */
class DictionaryAccessAdminResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
        ];
    }

}
