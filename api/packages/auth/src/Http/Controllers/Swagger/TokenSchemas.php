<?php

namespace Ulams\Auth\Http\Controllers\Swagger;

/**
 * Reusable OpenAPI schemas of the scoped token endpoints (ADR 0074).
 *
 * @OA\Schema(schema="ApiToken", required={"id","name","scopes","kind","expires_at","revoked"},
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="scopes", type="array", @OA\Items(type="string", example="courses:write")),
 *     @OA\Property(property="kind", type="string", enum={"cli","agent","ci","integration"}),
 *     @OA\Property(property="agent_name", type="string", nullable=true),
 *     @OA\Property(property="created_via", type="string", enum={"admin","cli","device"}),
 *     @OA\Property(property="rate_limit_per_minute", type="integer", nullable=true),
 *     @OA\Property(property="user_id", type="integer", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="expires_at", type="string", format="date-time"),
 *     @OA\Property(property="last_used_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="last_used_ip", type="string", nullable=true),
 *     @OA\Property(property="revoked", type="boolean"))
 *
 * @OA\Schema(schema="ApiTokenCreated", allOf={@OA\Schema(ref="#/components/schemas/ApiToken")},
 *     @OA\Property(property="token", type="string", description="The secret, prefixed ulams_pat_. Shown once.", example="ulams_pat_eyJ0eXAi..."))
 *
 * @OA\Schema(schema="ApiTokenList", @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ApiToken")),
 *     @OA\Property(property="meta", type="object"))
 *
 * @OA\Schema(schema="AgentAuditEntry",
 *     @OA\Property(property="id", type="integer"), @OA\Property(property="token_id", type="string", nullable=true),
 *     @OA\Property(property="user_id", type="integer", nullable=true), @OA\Property(property="agent_name", type="string", nullable=true),
 *     @OA\Property(property="client", type="string", nullable=true), @OA\Property(property="user_agent", type="string", nullable=true),
 *     @OA\Property(property="method", type="string"), @OA\Property(property="route_name", type="string", nullable=true),
 *     @OA\Property(property="path", type="string"), @OA\Property(property="route_params", type="object", nullable=true),
 *     @OA\Property(property="status", type="integer"), @OA\Property(property="dry_run", type="boolean"),
 *     @OA\Property(property="idempotency_key", type="string", nullable=true), @OA\Property(property="request_id", type="string", nullable=true),
 *     @OA\Property(property="duration_ms", type="integer", nullable=true), @OA\Property(property="ip", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"))
 *
 * @OA\Schema(schema="AgentAuditList", @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AgentAuditEntry")),
 *     @OA\Property(property="meta", type="object"))
 *
 * @OA\Schema(schema="ScopeError", @OA\Property(property="success", type="boolean", example=false), @OA\Property(property="message", type="string"),
 *     @OA\Property(property="error", type="string", enum={"scope_missing","scope_forbidden","scope_unmapped","rate_limited"}),
 *     @OA\Property(property="required", type="array", @OA\Items(type="string", example="courses:write")))
 */
final class TokenSchemas
{
}
