<?php

namespace Ulams\Core\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Ulams\Core\Models\CspReport */
class CspReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'directive' => $this->directive,
            'blocked_host' => $this->blocked_host,
            'document_path' => $this->document_path,
            'count' => $this->count,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
