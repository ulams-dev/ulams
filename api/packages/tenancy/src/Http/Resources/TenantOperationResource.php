<?php

namespace Ulams\Tenancy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Tenancy\Models\TenantOperation;

/**
 * The state of a tenant creation or deletion, polled by `ulams tenants … --wait`.
 *
 * @property TenantOperation $resource
 */
class TenantOperationResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $o = $this->resource;

        return [
            'id' => $o->id,
            'kind' => $o->kind,
            'status' => $o->status,
            'tenant' => $o->tenant_slug,
            'steps' => array_values($o->steps ?? []),
            'error' => $o->error,
            'requested_by' => $o->requested_by,
            'created_at' => $o->created_at?->toIso8601String(),
            'started_at' => $o->started_at?->toIso8601String(),
            'finished_at' => $o->finished_at?->toIso8601String(),
        ];
    }
}
