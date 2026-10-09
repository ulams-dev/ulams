import type { DemoConfig } from '@ulams/demo';
import { demoConfigFrom, demoLogin } from '@ulams/demo';

declare const REACT_APP_API_URL: string;

const DISABLED: DemoConfig = { enabled: false, frontUrl: null, adminUrl: null };

const apiUrl = () => window.REACT_APP_API_URL || REACT_APP_API_URL;

/**
 * Demo tenants (`ulams_demo.enabled` in `GET /api/config`, see api/packages/demo): when no
 * one is logged in, logs in as the tenant's demo admin and stores the token like the login
 * page does, so the login screen is skipped. Runs once per page load, before the current user
 * is fetched; after a logout the login screen stays until the next reload.
 *
 * Plain `fetch` on purpose: the umi request error handler would turn a failure into a
 * redirect to /404.
 */
export async function ensureDemoLogin(): Promise<DemoConfig> {
  let demo = DISABLED;
  try {
    const response = await fetch(`${apiUrl()}/api/config`, {
      headers: { Accept: 'application/json' },
    });
    const body = response.ok ? await response.json() : null;
    demo = demoConfigFrom(body?.data);
  } catch (error) {
    return DISABLED;
  }

  if (demo.enabled && !localStorage.getItem('TOKEN')) {
    try {
      localStorage.setItem('TOKEN', await demoLogin(apiUrl(), 'admin'));
      dispatchEvent(new Event('token_change'));
    } catch (error) {
      console.warn(error);
    }
  }

  return demo;
}
