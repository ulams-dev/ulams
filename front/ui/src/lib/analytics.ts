/** Analytics is restricted to ULAMS-owned public sites, never customer deployments. */
export const UMAMI_WEBSITE_ID = "3579de4c-ad8a-40ed-ba03-271286fb335e";
export const UMAMI_SCRIPT_ORIGIN = "https://cloud.umami.is";
export const UMAMI_COLLECT_ORIGIN = "https://gateway.umami.is";
export const UMAMI_DEMO_HOSTS = ["coffee", "oncall", "nightsky", "gravity", "poland", "ulam"].map((slug) => `${slug}.ulams.app`);
export const UMAMI_HOSTS = ["ulams.app", "www.ulams.app", "ulams-dev.github.io", ...UMAMI_DEMO_HOSTS];
export const umamiEnabled = (host: string): boolean => UMAMI_HOSTS.includes(host.toLowerCase().split(":")[0] ?? "");

/** A scroll to the demo section is not a demo opening. */
export function ctaEvent(href: string): string | undefined {
  if (href === "#demos" || /\/#demos$/.test(href)) return "demos_scroll";
  try {
    const url = new URL(href);
    if (UMAMI_DEMO_HOSTS.includes(url.hostname)) return "demo_open";
    if (UMAMI_DEMO_HOSTS.some((host) => url.hostname === host.replace(".ulams.app", "-admin.ulams.app"))) return "demo_admin_open";
    if (url.hostname === "github.com") return "github_open";
    if (url.hostname === "ulams-dev.github.io") return "docs_open";
  } catch { /* Relative links other than the demo anchor are not conversion events. */ }
  return undefined;
}
