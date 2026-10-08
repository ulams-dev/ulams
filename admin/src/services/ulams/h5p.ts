import type { AxiosRequestConfig } from '@umijs/max';
import { request } from 'umi';

/*
 * H5P lives in two places:
 * - Laravel keeps the admin list (with usage counts) under /api/admin/h5p/*;
 * - everything else goes straight to the H5P service, served same-origin
 *   under the API host at /h5p/* (Lumi h5p-nodejs-library, see api/h5p).
 * `request` prefixes REACT_APP_API_URL and adds the Bearer token.
 */

/** Laravel: paginated list, `per_page=0` returns everything */
export async function h5p(params: API.H5PListParams, options?: AxiosRequestConfig) {
  return request<API.DefaultMetaResponse<API.H5PContentListItem>>(`/api/admin/h5p/contents`, {
    method: 'GET',
    params,
    ...(options || {}),
  });
}

export async function allContent(options?: AxiosRequestConfig) {
  return h5p({ per_page: 0 }, options);
}

/** Laravel: deletes H5P contents that no topic uses */
export async function removeUnusedH5P(options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<unknown>>(`/api/admin/h5p/unused`, {
    method: 'DELETE',
    ...(options || {}),
  });
}

/*
 * Playing and editing happen inside the H5P service's embed pages
 * (components/H5P: H5PFrame / H5PEditorFrame), which load and save content
 * themselves; the admin only frames them.
 */

export async function removeH5P(id: string | number) {
  return request<API.DefaultResponse<{ contentId: string }>>(`/h5p/contents/${id}`, {
    method: 'DELETE',
  });
}

/** multipart field `h5p_file` → `{contentId, metadata, installedLibraries}` */
export const H5P_UPLOAD_URL = '/h5p/contents/upload';

/** .h5p package (fetch with the Bearer token, e.g. AuthenticatedLinkButton) */
export const h5pDownloadUrl = (id: string | number) => `/h5p/contents/${id}/download`;

/* ---- library administration (Lumi routers, plain JSON, no envelope) ---- */

export async function h5pLibraries(options?: AxiosRequestConfig) {
  return request<API.H5PLibraryAdministrationItem[]>(`/h5p/libraries`, {
    method: 'GET',
    ...(options || {}),
  });
}

/** library package upload, multipart field `file` → `{installed, updated}` */
export const H5P_LIBRARY_UPLOAD_URL = '/h5p/libraries';

export async function setH5PLibraryRestricted(ubername: string, restricted: boolean) {
  return request<void>(`/h5p/libraries/${encodeURIComponent(ubername)}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    data: { restricted },
  });
}

export async function removeH5PLibrary(ubername: string) {
  return request<void>(`/h5p/libraries/${encodeURIComponent(ubername)}`, {
    method: 'DELETE',
  });
}

export async function h5pContentTypeCacheStatus(options?: AxiosRequestConfig) {
  return request<{ lastUpdate: string | null }>(`/h5p/content-type-cache/update`, {
    method: 'GET',
    ...(options || {}),
  });
}

export async function updateH5PContentTypeCache() {
  return request<{ lastUpdate: string | null }>(`/h5p/content-type-cache/update`, {
    method: 'POST',
  });
}

export const libraryUbername = (lib: API.H5PLibraryAdministrationItem) =>
  `${lib.machineName}-${lib.majorVersion}.${lib.minorVersion}`;
