<?php

namespace Ulams\Auth\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Auth\Support\IssuedToken;

/**
 * The create response: the token list item plus the secret, which is shown exactly once.
 *
 * @property IssuedToken $resource
 */
class ApiTokenCreatedResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        return ApiTokenResource::make($this->resource->meta)->toArray($request) + ['token' => $this->resource->secret];
    }
}
