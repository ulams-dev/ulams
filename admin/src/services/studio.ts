/**
 * The AI Course Builder lives in the reference web app (front/web, /studio), not in the admin.
 * Its address: window.REACT_APP_STUDIO_URL when set (runtime env), else derived from the admin host
 * (`coffee.admin.localhost` → `coffee.app.localhost:4321` locally, `coffee.admin.example` →
 * `coffee.app.example` elsewhere).
 */
export function studioUrl(path = '/studio/new'): string {
  const configured = window.REACT_APP_STUDIO_URL;
  if (configured) return configured.replace(/\/+$/, '') + path;
  const { protocol, hostname } = window.location;
  const host = hostname.includes('.admin.')
    ? hostname.replace('.admin.', '.app.')
    : `app.${hostname}`;
  const port = host.endsWith('.localhost') ? ':4321' : '';
  return `${protocol}//${host}${port}${path}`;
}
