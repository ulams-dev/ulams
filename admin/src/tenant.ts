import { DEFAULT_ADMIN_TENANT_PATTERN, resolveApiUrl } from '@ulams/tenant';

declare const REACT_APP_API_URL: string;
declare const REACT_APP_TENANT_API_HOST_PATTERN: string;

/**
 * Resolves the API base URL once, before any module reads it.
 *
 * Order: runtime-injected `window.REACT_APP_API_URL` (Docker image env) → tenant API
 * derived from the admin host (`{slug}.admin.localhost` → `http://{slug}.localhost`)
 * → build-time `REACT_APP_API_URL` (the platform API, e.g. `http://api.localhost`).
 * Everything else keeps reading `window.REACT_APP_API_URL || REACT_APP_API_URL`.
 */
if (typeof window !== 'undefined') {
  const apiUrl = resolveApiUrl({
    runtime: window.REACT_APP_API_URL,
    hostname: window.location.hostname,
    // runtime-injected (Docker image env) first, then the build-time value
    pattern: window.REACT_APP_TENANT_API_HOST_PATTERN || REACT_APP_TENANT_API_HOST_PATTERN,
    defaultPattern: DEFAULT_ADMIN_TENANT_PATTERN,
    buildTime: REACT_APP_API_URL,
  });
  if (apiUrl) {
    window.REACT_APP_API_URL = apiUrl;
  }
}

export {};
