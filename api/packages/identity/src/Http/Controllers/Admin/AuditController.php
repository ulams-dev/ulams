<?php

namespace Ulams\Identity\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Identity\Enums\IdentityPermissionEnum;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Services\IdentityAuditLog;

/**
 * The identity audit trail (permission `identity_audit_read`): hash-chained, append-only, never holds tokens,
 * assertions or secrets.
 *
 * @OA\Schema(schema="IdentityAuditEntry", type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="actor", type="string", enum={"user","scim","hris","system","idp"}),
 *     @OA\Property(property="actor_id", type="integer", nullable=true),
 *     @OA\Property(property="provider_id", type="string", format="uuid", nullable=true),
 *     @OA\Property(property="action", type="string", example="sso.login"),
 *     @OA\Property(property="subject_type", type="string", nullable=true),
 *     @OA\Property(property="subject_id", type="string", nullable=true),
 *     @OA\Property(property="data", type="object", nullable=true, description="Reason codes and ids only"),
 *     @OA\Property(property="ip", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="hash", type="string"))
 *
 * @OA\Get(path="/api/admin/identity/audit", operationId="identity.audit.list", summary="Identity audit trail, newest first", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Parameter(name="action", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="provider_id", in="query", @OA\Schema(type="string", format="uuid")),
 *     @OA\Parameter(name="before_id", in="query", description="Page: entries older than this id", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", minimum=1, maximum=200, default=50)),
 *     @OA\Response(response=200, description="Entries", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/IdentityAuditEntry")))),
 *     @OA\Response(response=403, description="Needs identity_audit_read"))
 * @OA\Get(path="/api/admin/identity/audit/verify", operationId="identity.audit.verify", summary="Recompute the hash chain of the audit trail", tags={"Admin Identity"}, security={{"passport": {}}},
 *     @OA\Response(response=200, description="Result", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
 *         @OA\Property(property="data", type="object",
 *             @OA\Property(property="ok", type="boolean"),
 *             @OA\Property(property="checked", type="integer"),
 *             @OA\Property(property="broken_id", type="integer", nullable=true),
 *             @OA\Property(property="reason", type="string", nullable=true)))),
 *     @OA\Response(response=403, description="Needs identity_audit_read"))
 */
class AuditController extends UlamsBaseController
{
    public function __construct(private readonly IdentityAuditLog $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeRead($request);
        $data = $request->validate([
            'action' => ['nullable', 'string', Rule::in(IdentityAuditLog::ACTIONS)],
            'provider_id' => ['nullable', 'uuid'],
            'before_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $rows = IdentityAudit::query()
            ->when($data['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($data['provider_id'] ?? null, fn ($q, $v) => $q->where('provider_id', $v))
            ->when($data['before_id'] ?? null, fn ($q, $v) => $q->where('id', '<', $v))
            ->orderByDesc('id')->limit((int) ($data['limit'] ?? 50))->get();

        return $this->sendResponse($rows->map(fn (IdentityAudit $r) => [
            'id' => $r->id,
            'actor' => $r->actor,
            'actor_id' => $r->actor_id,
            'provider_id' => $r->provider_id,
            'action' => $r->action,
            'subject_type' => $r->subject_type,
            'subject_id' => $r->subject_id,
            'data' => $r->data,
            'ip' => $r->ip,
            'created_at' => $r->created_at?->toIso8601String(),
            'hash' => $r->hash,
        ])->all());
    }

    public function verify(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        return $this->sendResponse($this->audit->verify());
    }

    private function authorizeRead(Request $request): void
    {
        if (!$request->user()?->can(IdentityPermissionEnum::AUDIT_READ)) {
            throw new AccessDeniedHttpException('The identity audit trail needs the identity_audit_read permission.');
        }
    }
}
