<?php

namespace Ulams\Auth\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Auth\Models\AgentAuditLog;

/** @property AgentAuditLog $resource */
class AgentAuditResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        $row = $this->resource;

        return [
            'id' => $row->id,
            'token_id' => $row->token_id,
            'user_id' => $row->user_id,
            'agent_name' => $row->agent_name,
            'client' => $row->client,
            'user_agent' => $row->user_agent,
            'method' => $row->method,
            'route_name' => $row->route_name,
            'path' => $row->path,
            'route_params' => $row->route_params,
            'status' => $row->status,
            'dry_run' => $row->dry_run,
            'idempotency_key' => $row->idempotency_key,
            'request_id' => $row->request_id,
            'duration_ms' => $row->duration_ms,
            'ip' => $row->ip,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
