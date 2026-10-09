<?php

namespace Ulams\Auth\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Ulams\Auth\Models\AgentAuditLog;
use Ulams\Auth\Support\TokenContext;
use Ulams\Auth\Support\TokenScopes;

/**
 * Writes the append-only agent audit log (ADR 0074, docs/plans/cli.md 6.3): one row per request made
 * with a scoped token that changes something, plus reads of the `users` and `reports` areas
 * (personal data). Request and response bodies are never stored. Runs after the response is sent;
 * a failure to log never fails the request. Also keeps `last_used_at` / `last_used_ip` fresh.
 */
class RecordAgentAudit
{
    private const TOUCH_AFTER_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('ulams.audit_started', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $context = TokenContext::resolve($request);
            if ($context === null) {
                return;
            }
            $this->touch($context, $request);

            $write = !in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true);
            $area = $request->attributes->get('ulams.scope_area');
            if (!$write && !(is_string($area) && TokenScopes::auditsReads($area))) {
                return;
            }

            $route = $request->route();
            $started = (float) $request->attributes->get('ulams.audit_started', microtime(true));
            AgentAuditLog::query()->create([
                'token_id' => $context->tokenId,
                'user_id' => $context->user?->getAuthIdentifier(),
                'agent_name' => $context->meta->agent_name,
                'client' => $this->limit($request->header('X-Ulams-Client'), 32),
                'user_agent' => $this->limit($request->userAgent(), 255),
                'method' => $request->getMethod(),
                'route_name' => $route?->getName(),
                'path' => $this->limit('/' . ltrim($request->path(), '/'), 500),
                'route_params' => $route !== null ? $this->params($route->parameters()) : null,
                'status' => $response->getStatusCode(),
                'dry_run' => filter_var($request->header('X-Ulams-Dry-Run'), FILTER_VALIDATE_BOOLEAN),
                'idempotency_key' => $this->limit($request->header('Idempotency-Key'), 255),
                'request_id' => $this->limit($request->attributes->get('ulams.request_id') ?? $request->header('X-Request-Id'), 64),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'ip' => $request->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function touch(TokenContext $context, Request $request): void
    {
        $meta = $context->meta;
        if ($meta->last_used_at !== null && $meta->last_used_at->diffInSeconds(now(), true) < self::TOUCH_AFTER_SECONDS && $meta->last_used_ip === $request->ip()) {
            return;
        }
        $meta->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
    }

    /** @param array<string,mixed> $parameters */
    private function params(array $parameters): array
    {
        $out = [];
        foreach ($parameters as $name => $value) {
            $out[$name] = $value instanceof Model ? $value->getKey() : (is_scalar($value) ? $value : null);
        }

        return $out;
    }

    private function limit(mixed $value, int $max): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, $max) : null;
    }
}
