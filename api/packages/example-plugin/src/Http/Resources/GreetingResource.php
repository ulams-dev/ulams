<?php

namespace Ulams\ExamplePlugin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="ExamplePluginGreetingResource",
 *      @OA\Property(property="greeting", type="string", example="Hello from the example plugin, Ada"),
 *      @OA\Property(property="user_id", type="integer", nullable=true)
 * )
 */
class GreetingResource extends JsonResource
{
    public function __construct(private string $greeting, private ?int $userId = null)
    {
        parent::__construct(null);
    }

    public function toArray($request): array
    {
        return [
            'greeting' => $this->greeting,
            'user_id' => $this->userId,
        ];
    }
}
