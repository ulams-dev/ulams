<?php

namespace Ulams\Auth\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Ulams\Auth\Http\Controllers\Admin\Swagger\TokenAdminSwagger;
use Ulams\Auth\Http\Requests\Tokens\AdminListTokensRequest;
use Ulams\Auth\Http\Requests\Tokens\AgentAuditListRequest;
use Ulams\Auth\Http\Requests\Tokens\RevokeTokenRequest;
use Ulams\Auth\Http\Requests\Tokens\TokenAuditRequest;
use Ulams\Auth\Http\Resources\AgentAuditResource;
use Ulams\Auth\Http\Resources\ApiTokenResource;
use Ulams\Auth\Models\AgentAuditLog;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;

/** Admin view of every user's scoped tokens and of the agent audit log (permission `token_manage`). */
class TokenAdminController extends UlamsBaseController implements TokenAdminSwagger
{
    public function __construct(private PersonalAccessTokenServiceContract $tokens)
    {
    }

    public function index(AdminListTokensRequest $request): JsonResponse
    {
        $page = $this->tokens->paginateAll(
            $request->filled('user_id') ? (int) $request->input('user_id') : null,
            $request->input('kind'),
            $request->boolean('include_revoked'),
            (int) $request->input('per_page', 25),
        );

        return $this->sendResponseForResource(ApiTokenResource::collection($page), __('Token list'));
    }

    public function destroy(RevokeTokenRequest $request): JsonResponse
    {
        $this->tokens->revoke($request->tokenMeta());

        return $this->sendSuccess(__('Token revoked'));
    }

    public function audit(TokenAuditRequest $request): JsonResponse
    {
        $page = AgentAuditLog::query()->where('token_id', $request->tokenMeta()->token_id)
            ->orderByDesc('id')->paginate((int) $request->input('per_page', 50));

        return $this->sendResponseForResource(AgentAuditResource::collection($page), __('Token audit log'));
    }

    public function agentAudit(AgentAuditListRequest $request): JsonResponse
    {
        $query = AgentAuditLog::query()->orderByDesc('id');
        foreach (['token_id', 'user_id'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->input($column));
            }
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        return $this->sendResponseForResource(AgentAuditResource::collection($query->paginate((int) $request->input('per_page', 50))), __('Agent audit log'));
    }
}
