<?php

namespace Ulams\Tenancy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Tenancy\Models\TenantTemplate;

/**
 * A tenant template as the platform API shows it: of the settings only the names of the keys, never the values.
 *
 * @property TenantTemplate $resource
 */
class TenantTemplateResource extends JsonResource
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
            'users' => (int) $t->users,
            'env_override_keys' => array_keys((array) ($t->env_overrides ?? [])),
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }
}
