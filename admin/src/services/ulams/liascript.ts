import { request } from 'umi';

/** LiaScript sources (api/packages/liascript, permission liascript_manage). */

export type LiaScriptDocument = {
  id: number;
  title: string;
  current_version: number;
  versions_count?: number;
  assets?: string[];
  warnings?: string[];
  updated_at?: string;
};

export type LiaScriptVersion = {
  version: number;
  change_note: string | null;
  author_id: number | null;
  restored_from: number | null;
  size: number;
  assets: string[];
  created_at: string | null;
};

type Response<T> = { success: boolean; data: T; message: string };

export const liascriptDocuments = (params: { current?: number; pageSize?: number } = {}) =>
  request<Response<LiaScriptDocument[]> & { meta: { total: number } }>('/api/admin/liascript', {
    method: 'GET',
    params: { page: params.current, per_page: params.pageSize },
  });

export const liascriptDocument = (id: number) =>
  request<Response<LiaScriptDocument>>(`/api/admin/liascript/${id}`, { method: 'GET' });

/** Create from Markdown, or from an .md/.zip file (multipart). */
export const createLiaScript = (data: { title?: string; markdown?: string; file?: File }) => {
  if (data.file) {
    const form = new FormData();
    form.append('file', data.file);
    if (data.title) form.append('title', data.title);
    return request<Response<LiaScriptDocument>>('/api/admin/liascript', {
      method: 'POST',
      data: form,
    });
  }
  return request<Response<LiaScriptDocument>>('/api/admin/liascript', { method: 'POST', data });
};

export const renameLiaScript = (id: number, title: string) =>
  request<Response<LiaScriptDocument>>(`/api/admin/liascript/${id}`, {
    method: 'PUT',
    data: { title },
  });

export const addLiaScriptVersion = (id: number, markdown: string, changeNote?: string) =>
  request<Response<LiaScriptDocument>>(`/api/admin/liascript/${id}/versions`, {
    method: 'POST',
    data: { markdown, change_note: changeNote || undefined },
  });

export const liascriptVersions = (id: number) =>
  request<Response<LiaScriptVersion[]>>(`/api/admin/liascript/${id}/versions`, { method: 'GET' });

export const liascriptSource = (id: number, version?: number) =>
  request<string>(`/api/admin/liascript/${id}/source`, {
    method: 'GET',
    params: version ? { version } : {},
    responseType: 'text',
  });

export const restoreLiaScriptVersion = (id: number, version: number) =>
  request<Response<LiaScriptDocument>>(`/api/admin/liascript/${id}/versions/${version}/restore`, {
    method: 'POST',
  });

export const deleteLiaScript = (id: number) =>
  request<Response<null>>(`/api/admin/liascript/${id}`, { method: 'DELETE' });
