<?php

namespace Ulams\Auth\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Auth\Models\ApiTokenMeta;

/**
 * A scoped token as shown in lists. The secret is never part of it: it is returned once, by the
 * create call (`ApiTokenCreatedResource`).
 *
 * @property ApiTokenMeta $resource
 */
class ApiTokenResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        $meta = $this->resource;
        $token = $meta->token;

        return [
            'id' => $meta->token_id,
            'name' => $token?->name,
            'scopes' => array_values((array) ($token?->scopes ?? [])),
            'kind' => $meta->kind,
            'agent_name' => $meta->agent_name,
            'created_via' => $meta->created_via,
            'rate_limit_per_minute' => $meta->rate_limit_per_minute,
            'user_id' => $token?->user_id !== null ? (int) $token->user_id : null,
            'created_at' => $token?->created_at?->toIso8601String(),
            'expires_at' => $token?->expires_at?->toIso8601String(),
            'last_used_at' => $meta->last_used_at?->toIso8601String(),
            'last_used_ip' => $meta->last_used_ip,
            'revoked' => (bool) ($token?->revoked ?? false),
        ];
    }
}
