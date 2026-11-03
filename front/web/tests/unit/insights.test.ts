import { afterEach, describe, expect, it, vi } from "vitest";
import { eraseInsights, exportInsights, getInsights, parseSettingsForm, saveSettings, statusText } from "../../src/lib/insights.ts";

const tenant = { apiUrl: "http://coffee.localhost" };
const form = (entries: Array<[string, string]>) => {
  const f = new FormData();
  for (const [k, v] of entries) f.append(k, v);
  return f;
};
const respond = (status: number, body: unknown = {}) => new Response(typeof body === "string" ? body : JSON.stringify(body), { status });

afterEach(() => vi.unstubAllGlobals());

describe("parseSettingsForm", () => {
  it("reads every switch by presence, because an unchecked box is not posted", () => {
    expect(parseSettingsForm(form([["action", "save-settings"], ["enabled", "on"], ["nudges", "on"]]), false)).toEqual({ enabled: true, help: false, nudges: true });
  });

  it("adds consent only when the academy asks for it", () => {
    expect(parseSettingsForm(form([["enabled", "on"], ["consent", "on"]]), true)).toMatchObject({ consent: true });
    expect(parseSettingsForm(form([["enabled", "on"]]), true)).toMatchObject({ consent: false });
    expect(parseSettingsForm(form([["enabled", "on"], ["consent", "on"]]), false)).not.toHaveProperty("consent");
  });

  it("deletes earlier records only together with an opt-out", () => {
    expect(parseSettingsForm(form([["erase_existing", "on"]]), false)).toEqual({ enabled: false, help: false, nudges: false, eraseExisting: true });
    expect(parseSettingsForm(form([["enabled", "on"], ["erase_existing", "on"]]), false)).not.toHaveProperty("eraseExisting");
  });
});

describe("statusText", () => {
  it("says it in words and never relies on colour", () => {
    expect(statusText("on_track").label).toBe("On track");
    expect(statusText("struggling").label).toBe("Needs a closer look");
    expect(statusText("at_risk").label).toBe("Taking a break");
    expect(statusText("whatever")).toEqual({ label: "whatever", help: "" });
  });
});

describe("API calls", () => {
  it("loads the learner's own view with their token", async () => {
    const fetcher = vi.fn().mockResolvedValue(respond(200, { data: { enabled: true, settings: { enabled: true, help: true, nudges: true, consent: null }, requiresConsent: false, courses: [], retention: { signalsDays: 180, historyDays: 365 } } }));
    vi.stubGlobal("fetch", fetcher);
    const result = await getInsights(tenant, "secret");
    expect(result.ok).toBe(true);
    expect(fetcher.mock.calls[0]![0]).toBe("http://coffee.localhost/api/learner-insights/me");
    expect((fetcher.mock.calls[0]![1] as RequestInit).headers).toMatchObject({ Authorization: "Bearer secret" });
  });

  it("tells an expired session from an unreachable server", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(respond(401)));
    expect(await getInsights(tenant, "t")).toEqual({ ok: false, reason: "unauthenticated" });
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("down")));
    expect(await getInsights(tenant, "t")).toEqual({ ok: false, reason: "unavailable" });
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(respond(200, {})));
    expect(await getInsights(tenant, "t")).toEqual({ ok: false, reason: "unavailable" });
  });

  it("saves, erases and exports through the learner endpoints", async () => {
    const fetcher = vi.fn().mockResolvedValueOnce(respond(200)).mockResolvedValueOnce(respond(200)).mockResolvedValueOnce(respond(200, "{\"userId\":1}")).mockResolvedValueOnce(respond(500));
    vi.stubGlobal("fetch", fetcher);

    expect(await saveSettings(tenant, "t", { enabled: false, help: false, nudges: false, eraseExisting: true })).toEqual({ ok: true });
    expect(await eraseInsights(tenant, "t")).toEqual({ ok: true });
    expect(await exportInsights(tenant, "t")).toEqual({ ok: true, body: "{\"userId\":1}" });
    expect(await exportInsights(tenant, "t")).toEqual({ ok: false, reason: "unavailable" });

    expect(fetcher.mock.calls[0]![0]).toBe("http://coffee.localhost/api/learner-insights/me/settings");
    expect((fetcher.mock.calls[0]![1] as RequestInit).method).toBe("PUT");
    expect(JSON.parse(String((fetcher.mock.calls[0]![1] as RequestInit).body))).toEqual({ enabled: false, help: false, nudges: false, eraseExisting: true });
    expect((fetcher.mock.calls[1]![1] as RequestInit).method).toBe("DELETE");
  });
});
