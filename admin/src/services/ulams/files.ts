import type { AxiosRequestConfig } from '@umijs/max';
import { request } from 'umi';
import { contentImageUrl } from './imageService';

/**  GET /api/currentUser */
export async function files(
  params: API.CourseParams & {
    // query
    directory: string;
    page?: number;
  },
  options?: AxiosRequestConfig,
) {
  return request<API.FileList>('/api/admin/file/list', {
    method: 'GET',
    params,
    ...(options || {}),
  });
}

export async function findFile(
  params: API.CourseParams & {
    directory: string;
    name: string;
    page?: number;
  },
  options?: AxiosRequestConfig,
) {
  return request<API.FileList>(`/api/admin/file/find`, {
    method: 'GET',
    params,
    ...(options || {}),
  });
}

/**  GET /api/currentUser */
export async function upload(file: File, directory: string, options?: AxiosRequestConfig) {
  const formData = new FormData();
  formData.append('file[]', file);
  formData.append('target', directory);

  return request<API.FileUpload>('/api/admin/file/upload', {
    method: 'POST',
    data: formData,
    ...(options || {}),
  });
}

/**
 * URL to store for an uploaded image. Default (`REACT_APP_IMAGE_SERVICE` unset or `php`): the API resizer URL,
 * exactly as before. With `cloudflare` or `noop` and a public `url`: that plain URL (see `imageService.ts`).
 */
export const resizedImage = (path: string, width = 1000, url?: string) =>
  contentImageUrl({ path, url }, width);

/**  GET /api/currentUser */
export async function remove(url: string, options?: AxiosRequestConfig) {
  return request<API.FileList>('/api/admin/file/delete', {
    method: 'DELETE',
    data: {
      url,
    },
    ...(options || {}),
  });
}
