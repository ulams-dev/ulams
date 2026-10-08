import {
  DEFAULT_FRONT_TENANT_PATTERN,
  resolveApiUrl,
  tenantFromHost,
} from "@ulams/tenant";

declare global {
  interface Window {
    VITE_APP_TENANT_API_HOST_PATTERN?: string;
  }
}

const hostname = () =>
  typeof window !== "undefined" ? window.location.hostname : undefined;

/**
 * Host pattern that maps a tenant front host to its API, e.g.
 * `{slug}.app.localhost=>http://{slug}.localhost` (default) or, in production,
 * `{slug}.ulams.app=>https://{slug}.api.ulams.app`. See src/lib/tenant/resolveApiUrl.ts.
 */
export const TENANT_API_HOST_PATTERN =
  (typeof window !== "undefined" && window.VITE_APP_TENANT_API_HOST_PATTERN) ||
  import.meta.env.VITE_APP_TENANT_API_HOST_PATTERN ||
  DEFAULT_FRONT_TENANT_PATTERN;

/** The tenant recognised from the current host, or null on the platform host. */
export const TENANT = tenantFromHost(hostname(), TENANT_API_HOST_PATTERN);

/**
 * API base URL: runtime-injected `window.VITE_APP_API_URL` → tenant API derived
 * from the host → build-time `VITE_APP_PUBLIC_API_URL` (the platform API).
 */
export const getAPIURL = (): string | null =>
  resolveApiUrl({
    runtime: typeof window !== "undefined" ? window.VITE_APP_API_URL : null,
    hostname: hostname(),
    pattern: TENANT_API_HOST_PATTERN,
    buildTime: import.meta.env.VITE_APP_PUBLIC_API_URL,
  });

// Empty when nothing is configured; index.tsx then renders a configuration error.
export const API_URL: string = getAPIURL() ?? "";

/** Public URL of this front (return URLs for payments, e-mail links): the tenant host when on one. */
export const APP_URL =
  window.VITE_APP_URL ||
  (TENANT ? window.location.origin : null) ||
  import.meta.env.VITE_APP_URL ||
  window.location.origin;

export const VITE_APP_FIREBASE_VAPID_KEY =
  window.VITE_APP_FIREBASE_VAPID_KEY ||
  import.meta.env.VITE_APP_FIREBASE_VAPID_KEY ||
  null;
export const VITE_APP_FIREBASE_APIKEY =
  window.VITE_APP_FIREBASE_APIKEY ||
  import.meta.env.VITE_APP_FIREBASE_APIKEY ||
  null;
export const VITE_APP_FIREBASE_AUTHDOMAIN =
  window.VITE_APP_FIREBASE_AUTHDOMAIN ||
  import.meta.env.VITE_APP_FIREBASE_AUTHDOMAIN ||
  null;
export const VITE_APP_FIREBASE_PROJECTID =
  window.VITE_APP_FIREBASE_PROJECTID ||
  import.meta.env.VITE_APP_FIREBASE_PROJECTID ||
  null;
export const VITE_APP_FIREBASE_STORAGEBUCKET =
  window.VITE_APP_FIREBASE_STORAGEBUCKET ||
  import.meta.env.VITE_APP_FIREBASE_STORAGEBUCKET ||
  null;
export const VITE_APP_FIREBASE_MESSAGINGSENDERID =
  window.VITE_APP_FIREBASE_MESSAGINGSENDERID ||
  import.meta.env.VITE_APP_FIREBASE_MESSAGINGSENDERID ||
  null;
export const VITE_APP_FIREBASE_APPID =
  window.VITE_APP_FIREBASE_APPID ||
  import.meta.env.VITE_APP_FIREBASE_APPID ||
  null;
export const VITE_APP_IOS_APIKEY =
  window.VITE_APP_IOS_APIKEY || import.meta.env.VITE_APP_IOS_APIKEY || null;
export const VITE_APP_ANDROID_APIKEY =
  window.VITE_APP_ANDROID_APIKEY ||
  import.meta.env.VITE_APP_ANDROID_APIKEY ||
  null;

export const VITE_APP_PUBLIC_IMG_URL =
  window.VITE_APP_PUBLIC_IMG_URL ||
  import.meta.env.VITE_APP_PUBLIC_IMG_URL ||
  null;

export const VITE_APP_PUBLIC_IMG_BUCKET_FOLDER =
  window.VITE_APP_PUBLIC_IMG_BUCKET_FOLDER ||
  import.meta.env.VITE_APP_PUBLIC_IMG_BUCKET_FOLDER ||
  "";
