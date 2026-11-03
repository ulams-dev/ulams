const OWN_HOSTS = /(^|\.)ulams\.app$|(^|\.)localhost$/;

/** True for absolute http(s) links that leave the ulams sites (documentation, GitHub, sources). */
export function isExternal(href: string | undefined): boolean {
  if (!href || !/^https?:\/\//i.test(href)) return false;
  try {
    return !OWN_HOSTS.test(new URL(href).hostname);
  } catch {
    return false;
  }
}

/** Anchor attributes that open external links in a new tab. */
export function linkTarget(href: string | undefined): { target?: "_blank"; rel?: string } {
  return isExternal(href) ? { target: "_blank", rel: "noopener noreferrer" } : {};
}
