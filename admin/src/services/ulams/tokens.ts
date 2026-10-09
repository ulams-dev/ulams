import type { AxiosRequestConfig } from '@umijs/max';
import { request } from 'umi';

export type ApiToken = {
  id: string;
  name: string;
  scopes: string[];
  kind: 'cli' | 'agent' | 'ci' | 'integration';
  agent_name: string | null;
  created_via: 'admin' | 'cli' | 'device';
  user_id: number | null;
  created_at: string;
  expires_at: string;
  last_used_at: string | null;
  last_used_ip: string | null;
  revoked: boolean;
};

export type AgentAuditEntry = {
  id: number;
  method: string;
  path: string;
  status: number;
  agent_name: string | null;
  client: string | null;
  dry_run: boolean;
  ip: string | null;
  created_at: string;
};

type Paged<T> = { success: boolean; data: T[]; meta: { total: number } };

/**  GET /api/admin/tokens */
export async function listTokens(
  params?: {
    page?: number;
    per_page?: number;
    user_id?: number;
    kind?: string;
    include_revoked?: boolean;
  },
  options?: AxiosRequestConfig,
) {
  return request<Paged<ApiToken>>(`/api/admin/tokens`, {
    method: 'GET',
    params,
    ...(options || {}),
  });
}

/**  DELETE /api/admin/tokens/{id} */
export async function revokeToken(id: string, options?: AxiosRequestConfig) {
  return request<{ success: boolean }>(`/api/admin/tokens/${id}`, {
    method: 'DELETE',
    ...(options || {}),
  });
}

/**  GET /api/admin/tokens/{id}/audit */
export async function tokenAudit(id: string, params?: { page?: number; per_page?: number }) {
  return request<Paged<AgentAuditEntry>>(`/api/admin/tokens/${id}/audit`, {
    method: 'GET',
    params,
  });
}

/**  POST /api/auth/tokens (own token; the secret is in `data.token`, shown once) */
export async function createToken(
  body: { name: string; scopes: string[]; expires_in_days: number; kind?: string },
  options?: AxiosRequestConfig,
) {
  return request<{ success: boolean; data: ApiToken & { token: string } }>(`/api/auth/tokens`, {
    method: 'POST',
    data: body,
    ...(options || {}),
  });
}
