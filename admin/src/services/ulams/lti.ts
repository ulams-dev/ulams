import { request } from 'umi';

/** LTI 1.3 registrations (api/packages/lti, permission lti_manage). */

export type LtiTool = {
  id: number;
  name: string;
  client_id: string;
  deployment_id: string;
  oidc_login_url: string;
  launch_url: string;
  deep_linking_url?: string | null;
  redirect_uris?: string[] | null;
  jwks_url?: string | null;
  public_key?: string | null;
  custom?: Record<string, string> | null;
  share_name: boolean;
  share_email: boolean;
  enabled: boolean;
};

export type LtiPlatform = {
  id: number;
  name: string;
  issuer: string;
  client_id: string;
  deployment_ids: string[];
  auth_login_url: string;
  auth_token_url: string;
  auth_server?: string | null;
  jwks_url: string;
  default_course_id?: number | null;
  enabled: boolean;
};

export type LtiEndpoints = {
  issuer: string;
  jwks_url: string;
  platform: { oidc_auth_url: string; token_url: string; deep_linking_return_url: string };
  tool: { oidc_login_url: string; launch_url: string; deep_linking_url: string };
};

type Response<T> = { success: boolean; data: T; message: string };

export const ltiEndpoints = () =>
  request<Response<LtiEndpoints>>('/api/admin/lti/endpoints', { method: 'GET' });

export const ltiTools = () =>
  request<Response<LtiTool[]>>('/api/admin/lti/tools', { method: 'GET' });

export const saveLtiTool = (tool: Partial<LtiTool>) =>
  request<Response<LtiTool>>(tool.id ? `/api/admin/lti/tools/${tool.id}` : '/api/admin/lti/tools', {
    method: tool.id ? 'PUT' : 'POST',
    data: tool,
  });

export const deleteLtiTool = (id: number) =>
  request<Response<null>>(`/api/admin/lti/tools/${id}`, { method: 'DELETE' });

/** Starts deep linking: returns the tool's login URL to open in a new window. */
export const startLtiDeepLink = (toolId: number, lessonId: number) =>
  request<Response<{ url: string; tool: string }>>(`/api/admin/lti/tools/${toolId}/deep-link`, {
    method: 'POST',
    data: { lesson_id: lessonId },
  });

export const ltiPlatforms = () =>
  request<Response<LtiPlatform[]>>('/api/admin/lti/platforms', { method: 'GET' });

export const saveLtiPlatform = (platform: Partial<LtiPlatform>) =>
  request<Response<LtiPlatform>>(
    platform.id ? `/api/admin/lti/platforms/${platform.id}` : '/api/admin/lti/platforms',
    { method: platform.id ? 'PUT' : 'POST', data: platform },
  );

export const deleteLtiPlatform = (id: number) =>
  request<Response<null>>(`/api/admin/lti/platforms/${id}`, { method: 'DELETE' });
