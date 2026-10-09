<?php

namespace Ulams\Tenancy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Tenancy\Models\Tenant;

/**
 * A tenant as the platform API shows it. Never a secret: no database password, app key, Passport
 * keys, and of the env overrides only the names of the keys.
 *
 * @property Tenant $resource
 */
class TenantResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $t = $this->resource;

        return [
            'slug' => $t->slug,
            'name' => $t->name,
            'theme' => $t->theme,
            'accent' => $t->accent,
            'demo' => (bool) $t->demo,
            'status' => $t->status,
            'urls' => ['api' => $t->apiUrl(), 'front' => $t->frontUrl(), 'admin' => $t->adminUrl()],
            'steps' => (object) ($t->steps ?? []),
            'env_override_keys' => array_keys((array) ($t->env_overrides ?? [])),
            'last_error' => $t->last_error,
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }
}
