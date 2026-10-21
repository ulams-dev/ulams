import axe from "axe-core";
import { afterEach, beforeAll, describe, expect, it, vi } from "vitest";
import { setupDom } from "./astro-render.ts";

setupDom();
const { badgeHref, isBadgeId, loadBadge } = await import("../src/elements/certificate-badge.ts");

const ID = "b3f1c2d4-1111-4222-8333-444455556666";
const record = { certificate_id: ID, status: "valid", title: "Safety at work", course_title: "Safety at work", holder_name: "Ada Lovelace", issued_at: "2026-03-01T10:00:00+00:00", expires_at: "2027-03-01T10:00:00+00:00", revoked_at: null, issuer: "Acme" };
const fetchMock = vi.fn();

beforeAll(() => {
  vi.stubGlobal("fetch", fetchMock);
});
afterEach(() => {
  document.body.innerHTML = "";
  document.documentElement.removeAttribute("lang");
  fetchMock.mockReset();
});

const answer = (status: number, data?: unknown) => new Response(JSON.stringify({ data }), { status });

async function mount(attrs: string, response?: Response | "offline") {
  if (response === "offline") fetchMock.mockRejectedValue(new TypeError("offline"));
  else if (response) fetchMock.mockResolvedValue(response);
  document.body.innerHTML = `<main><ulams-certificate-badge ${attrs}></ulams-certificate-badge></main>`;
  const el = document.body.querySelector("ulams-certificate-badge") as HTMLElement;
  await vi.waitFor(() => expect(el.shadowRoot?.querySelector("[role=status]")).toBeNull());
  return el;
}
const text = (el: HTMLElement) => el.shadowRoot!.textContent ?? "";

describe("helpers", () => {
  it("fills the link template and refuses script URLs", () => {
    expect(badgeHref("/certificates/verify/{id}", ID)).toBe(`/certificates/verify/${ID}`);
    expect(badgeHref("https://acme.test/v/{id}", ID)).toBe(`https://acme.test/v/${ID}`);
    expect(badgeHref("javascript:alert(1)", ID)).toBe("#");
    expect(badgeHref("//evil.test/{id}", ID)).toBe("#");
  });
  it("accepts only ids the endpoint can know", () => {
    expect(isBadgeId(ID)).toBe(true);
    expect(isBadgeId("../x")).toBe(false);
    expect(isBadgeId("short")).toBe(false);
  });
  it("tells unknown from unreachable and never throws", async () => {
    expect((await loadBadge("https://a.test", ID, (async () => answer(404)) as unknown as typeof fetch)).kind).toBe("unknown");
    expect((await loadBadge("https://a.test", ID, (async () => answer(500)) as unknown as typeof fetch)).kind).toBe("failed");
    expect((await loadBadge("https://a.test", ID, (async () => answer(200, { status: "weird" })) as unknown as typeof fetch)).kind).toBe("failed");
    expect((await loadBadge("https://a.test", ID, (async () => Promise.reject(new Error("x"))) as unknown as typeof fetch)).kind).toBe("failed");
  });
});

describe("<ulams-certificate-badge>", () => {
  it("shows a valid certificate with holder, course and dates and links to verification, with no token sent", async () => {
    const el = await mount(`certificate-id="${ID}" api-url="https://acme.test/" verify-href="https://acme.test/certificates/verify/{id}"`, answer(200, record));
    expect(fetchMock.mock.calls[0]![0]).toBe(`https://acme.test/api/certificates/verify/${ID}`);
    expect(JSON.stringify(fetchMock.mock.calls[0]![1])).not.toContain("Authorization");
    expect(text(el)).toContain("Valid");
    expect(text(el)).toContain("Ada Lovelace");
    expect(text(el)).toContain("Safety at work");
    expect(text(el)).toContain("1 March 2027");
    expect(el.shadowRoot!.querySelector("a")!.getAttribute("href")).toBe(`https://acme.test/certificates/verify/${ID}`);
    expect(el.shadowRoot!.querySelector("section")!.getAttribute("aria-label")).toBe("Certificate: Valid, Safety at work");
  });

  it("writes out expired and revoked, and omits the holder when the API does not return it", async () => {
    const expired = await mount(`certificate-id="${ID}" api-url="https://a.test"`, answer(200, { ...record, status: "expired", holder_name: null }));
    expect(text(expired)).toContain("Expired");
    expect(text(expired)).not.toContain("Holder");
    const revoked = await mount(`certificate-id="${ID}" api-url="https://a.test"`, answer(200, { ...record, status: "revoked", revoked_at: "2026-09-01T00:00:00+00:00", expires_at: null }));
    expect(text(revoked)).toContain("Revoked on 1 September 2026");
    expect(text(revoked)).not.toContain("Valid until");
  });

  it("never renders API text as HTML", async () => {
    const el = await mount(`certificate-id="${ID}" api-url="https://a.test"`, answer(200, { ...record, holder_name: '<img src=x onerror="alert(1)">' }));
    expect(el.shadowRoot!.querySelector("img")).toBeNull();
    expect(text(el)).toContain("<img");
  });

  it("explains a missing id, an unknown id and an unreachable API with a retry", async () => {
    const none = await mount(`api-url="https://a.test"`);
    expect(text(none)).toContain("Add a certificate-id");
    expect(fetchMock).not.toHaveBeenCalled();
    const unknown = await mount(`certificate-id="${ID}" api-url="https://a.test"`, answer(404));
    expect(unknown.shadowRoot!.querySelector("[role=alert]")!.textContent).toContain("not known");
    const down = await mount(`certificate-id="${ID}" api-url="https://a.test"`, "offline");
    expect(down.shadowRoot!.querySelector("[role=alert]")!.textContent).toContain("could not be checked");
    fetchMock.mockResolvedValue(answer(200, record));
    (down.shadowRoot!.querySelector("[role=alert] a") as HTMLAnchorElement).click();
    await vi.waitFor(() => expect(text(down)).toContain("Valid"));
  });

  it("follows the lang attribute, then the page language", async () => {
    const pl = await mount(`certificate-id="${ID}" api-url="https://a.test" lang="pl"`, answer(200, record));
    expect(text(pl)).toContain("Ważny");
    expect(text(pl)).toContain("marca 2027");
    document.documentElement.lang = "zh";
    const zh = await mount(`certificate-id="${ID}" api-url="https://a.test"`, answer(200, record));
    expect(text(zh)).toContain("有效");
  });

  it("passes axe for valid and revoked", async () => {
    for (const data of [record, { ...record, status: "revoked", revoked_at: "2026-09-01T00:00:00+00:00" }]) {
      const el = await mount(`certificate-id="${ID}" api-url="https://a.test"`, answer(200, data));
      const results = await axe.run(el.shadowRoot!.querySelector("section")!, { rules: { "color-contrast": { enabled: false } } });
      expect(results.violations.map((v) => v.id)).toEqual([]);
    }
  });
});
