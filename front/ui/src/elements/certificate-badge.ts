import { fill, formatDate, trainingLocale, trainingStrings, type TrainingLocale } from "../lib/training-strings.ts";

/**
 * <ulams-certificate-badge certificate-id="…" api-url="https://acme.ulams.app"></ulams-certificate-badge>
 *
 * Shows whether a certificate is valid, for whom and until when, by asking the public verification
 * endpoint (`GET /api/certificates/verify/{id}`, no token). It works on any page. The status is
 * always written out (never colour alone), the content renders in a shadow root and every value from
 * the API is set with textContent. Attributes:
 *
 *   certificate-id  the public certificate id (required)
 *   api-url         tenant API origin (required outside the API's own origin)
 *   verify-href     link template, `{id}` is replaced; default `/certificates/verify/{id}`
 *   lang            `en`, `pl` or `zh`; default the page language
 */
export interface BadgeRecord {
  certificate_id?: string;
  status?: string;
  title?: string | null;
  course_title?: string | null;
  holder_name?: string | null;
  issued_at?: string | null;
  expires_at?: string | null;
  revoked_at?: string | null;
  issuer?: string | null;
}

const STATUSES = ["valid", "expired", "revoked"] as const;
type Status = (typeof STATUSES)[number];

const STYLE = `
:host { display: block; color: var(--ulams-color-text, #1b1b1f); font: inherit; }
* { box-sizing: border-box; }
p, h2, dl, dd { margin: 0; }
.badge { display: grid; gap: 10px; max-width: 460px; padding: 16px; border: 1px solid var(--ulams-color-border, #d0d0d8); border-left-width: 6px; border-radius: var(--ulams-radius-card, 8px); background: var(--ulams-color-surface, #fff); }
.badge[data-status="valid"] { border-left-color: var(--ulams-color-positive, #1a7f4b); }
.badge[data-status="expired"] { border-left-color: var(--ulams-color-warning, #8a5a00); }
.badge[data-status="revoked"] { border-left-color: var(--ulams-color-error, #b3261e); }
h2 { font-size: 1.05rem; line-height: 1.3; }
dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 12px; font-size: 0.925rem; }
dt { color: var(--ulams-color-muted, #595965); }
dd { overflow-wrap: anywhere; }
.muted { color: var(--ulams-color-muted, #595965); font-size: 0.9rem; }
a { color: var(--ulams-color-primary-on-light, #1d4ed8); text-underline-offset: 3px; display: inline-flex; align-items: center; min-height: 24px; }
a:focus-visible { outline: 3px solid var(--ulams-color-primary, #1d4ed8); outline-offset: 2px; }
`;

const h = (tag: string, attrs: Record<string, string> = {}, ...kids: Array<Node | string>): HTMLElement => {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
  for (const kid of kids) el.append(kid);
  return el;
};

/** Fills `{id}`; only relative and http(s) templates are accepted, anything else becomes `#`. */
export function badgeHref(template: string, id: string): string {
  const ok = /^\/(?!\/)/.test(template) || /^https?:\/\//i.test(template);
  return ok ? template.replace("{id}", encodeURIComponent(id)) : "#";
}

/** The id format of the verification endpoint; anything else cannot exist, so the API is not asked. */
export const isBadgeId = (id: string): boolean => /^[A-Za-z0-9-]{8,64}$/.test(id);

export type BadgeState = { kind: "found"; record: BadgeRecord & { status: Status } } | { kind: "unknown" } | { kind: "failed" };

export async function loadBadge(apiUrl: string, id: string, doFetch: typeof fetch = (input, init) => fetch(input, init)): Promise<BadgeState> {
  try {
    const response = await doFetch(`${apiUrl.replace(/\/+$/, "")}/api/certificates/verify/${encodeURIComponent(id)}`, { headers: { Accept: "application/json" }, signal: AbortSignal.timeout(8_000) });
    if (response.status === 404) return { kind: "unknown" };
    if (!response.ok) return { kind: "failed" };
    const record = ((await response.json()) as { data?: BadgeRecord }).data;
    if (!record || !(STATUSES as readonly string[]).includes(record.status ?? "")) return { kind: "failed" };
    return { kind: "found", record: record as BadgeRecord & { status: Status } };
  } catch {
    return { kind: "failed" };
  }
}

class UlamsCertificateBadge extends HTMLElement {
  private root!: ShadowRoot;
  private seq = 0;

  static get observedAttributes(): string[] {
    return ["certificate-id", "api-url", "verify-href", "lang"];
  }

  connectedCallback(): void {
    this.root = this.shadowRoot ?? this.attachShadow({ mode: "open" });
    void this.reload();
  }

  attributeChangedCallback(): void {
    if (this.isConnected && this.root) void this.reload();
  }

  private get locale(): TrainingLocale {
    return trainingLocale(this.getAttribute("lang") ?? document.documentElement.lang);
  }

  private paint(body: Node): void {
    this.root.replaceChildren(h("style", {}, STYLE), body);
  }

  async reload(): Promise<void> {
    const mine = ++this.seq;
    const t = trainingStrings(this.locale);
    const id = (this.getAttribute("certificate-id") ?? "").trim();
    if (!id || !isBadgeId(id)) {
      this.paint(h("p", { role: "alert" }, id ? t.badgeUnknown : t.badgeMissingId));
      return;
    }
    this.paint(h("p", { class: "muted", role: "status" }, t.badgeLoading));
    const state = await loadBadge(this.getAttribute("api-url") ?? "", id);
    if (mine !== this.seq) return;
    if (state.kind === "failed") {
      const retry = h("a", { href: "#" }, t.retry);
      retry.addEventListener("click", (event) => {
        event.preventDefault();
        void this.reload();
      });
      this.paint(h("p", { role: "alert" }, `${t.badgeFailed} `, retry));
      return;
    }
    if (state.kind === "unknown") {
      this.paint(h("p", { role: "alert" }, t.badgeUnknown));
      return;
    }
    this.paint(this.card(state.record, id, t));
  }

  private card(record: BadgeRecord & { status: Status }, id: string, t: ReturnType<typeof trainingStrings>): HTMLElement {
    const locale = this.locale;
    const statusText = record.status === "valid" ? t.certValid : record.status === "expired" ? t.certExpired : fill(t.certRevoked, { date: formatDate(locale, record.revoked_at) }).trim();
    const course = record.course_title ?? record.title ?? "";
    const rows: Array<[string, string]> = [];
    if (course) rows.push([t.badgeCourse, course]);
    if (record.holder_name) rows.push([t.badgeHolder, record.holder_name]);
    if (record.issued_at) rows.push([t.badgeIssued, formatDate(locale, record.issued_at)]);
    if (record.status !== "revoked") rows.push([t.badgeExpires, record.expires_at ? formatDate(locale, record.expires_at) : t.doesNotExpire]);
    const dl = h("dl", {});
    for (const [term, value] of rows) dl.append(h("dt", {}, term), h("dd", {}, value));
    const href = badgeHref(this.getAttribute("verify-href") ?? "/certificates/verify/{id}", id);
    return h(
      "section",
      { class: "badge", "data-status": record.status, "aria-label": fill(t.badgeLabel, { status: statusText, course }) },
      h("h2", {}, statusText),
      dl,
      h("a", { href }, t.badgeVerifyLink)
    );
  }
}

if (!customElements.get("ulams-certificate-badge")) customElements.define("ulams-certificate-badge", UlamsCertificateBadge);
