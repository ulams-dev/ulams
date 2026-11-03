import type { AxiosRequestConfig } from '@umijs/max';
import { request } from 'umi';

export async function templates(params?: API.TemplatesParams, options?: AxiosRequestConfig) {
  return request<API.TemplateList>(`/api/admin/templates`, {
    method: 'GET',
    useCache: false,
    params,
    ...(options || {}),
  });
}

export async function template(id: number, options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<API.Template>>(`/api/admin/templates/${id}`, {
    method: 'GET',
    ...(options || {}),
  });
}

export async function createTemplate(body?: Partial<API.Template>, options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<API.Template>>(`/api/admin/templates`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    data: body,
    ...(options || {}),
  });
}

export async function updateTemplate(
  id: number,
  body?: Partial<API.Template>,
  options?: AxiosRequestConfig,
) {
  return request<API.DefaultResponse<API.Template>>(`/api/admin/templates/${id}`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
    },
    data: body,
    ...(options || {}),
  });
}

export async function deleteTemplate(id: number, options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<API.Template>>(`/api/admin/templates/${id}`, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
    },
    ...(options || {}),
  });
}

export async function variables(options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<API.TemplateVariables>>(`/api/admin/templates/variables`, {
    method: 'GET',
    ...(options || {}),
  });
}

export async function preview(id: number, options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<Record<string, string>>>(
    `/api/admin/templates/${id}/preview`,
    {
      method: 'GET',
      ...(options || {}),
    },
  );
}

export type EmailDraftBody = { event: string; title: string; content: string };

export async function renderEmailDraft(body: EmailDraftBody, options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<API.EmailTemplateDraft>>(`/api/admin/templates/email/render`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    data: body,
    ...(options || {}),
  });
}

export async function testSendEmailDraft(body: EmailDraftBody, options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<API.EmailTemplateDraft>>(
    `/api/admin/templates/email/test-send`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      data: body,
      ...(options || {}),
    },
  );
}

export type EmailImage = {
  url: string;
  name: string;
  mime: string;
  size: number;
  width: number;
  height: number;
};

/** Uploads an image of an e-mail template to the storage of the current tenant. */
export async function uploadEmailImage(file: File, options?: AxiosRequestConfig) {
  const data = new FormData();
  data.append('image', file);
  return request<API.DefaultResponse<EmailImage>>(`/api/admin/templates/email/images`, {
    method: 'POST',
    data,
    ...(options || {}),
  });
}

/// api/admin/events/trigger-manually/{id}

export async function triggerManualEvent(
  id: number,

  users: number[],

  options?: AxiosRequestConfig,
) {
  return request<API.DefaultResponse<API.Template>>(`/api/admin/events/trigger-manually/${id}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    data: {
      users,
    },
    ...(options || {}),
  });
}

// /api/admin/products/{id}/trigger-event-manually/{idTemplate}

export async function triggerManualEventForProduct(
  templateId: number,
  productId: number,
  options?: AxiosRequestConfig,
) {
  return request<API.DefaultResponse<API.Template>>(
    `/api/admin/products/${productId}/trigger-event-manually/${templateId}`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      ...(options || {}),
    },
  );
}
