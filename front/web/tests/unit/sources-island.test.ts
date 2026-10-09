// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";

const S = "01m4fj5zsgfxhzh3cantwevncw";
const counts = { changed: 1, moved: 0, removed: 0, added: 1, trivial: 1, minor: 1, substantive: 0, total: 3 };
const rev = (n: number, extra: Record<string, unknown> = {}) => ({
  id: `rev${n}`, sourceId: "src1", number: n, origin: n === 1 ? "initial" : "upload", originRef: null, trigger: n === 1 ? "initial" : "manual",
  triggeredBy: 1, status: "ingested", fragmentCount: 3, tokens: 9, title: null, name: "handbook.md", files: null, counts: null, error: null,
  detectedAt: "2026-10-20T09:30:00+00:00", synced: n === 1, latest: n === 2, ...extra,
});
const frag = (text: string) => ({ fragmentId: "frg_aaaaaaaaaaa1", label: "§1 Intro", section: "1", headingPath: ["Intro"], file: null, text, pages: null });
const row = (id: number, magnitude: string) => ({
  id, kind: "changed", magnitude, similarity: 0.9, signals: magnitude === "minor" ? ["number"] : [],
  wordDiff: [["=", "Use "], ["-", "15"], ["+", "16"]], old: frag("Use 15"), new: frag("Use 16"),
});

function stubApi(uploadStatus = 201) {
  if (typeof AbortSignal.timeout !== "function") (AbortSignal as unknown as { timeout: () => AbortSignal }).timeout = () => new AbortController().signal;
  const calls: string[] = [];
  vi.stubGlobal("fetch", async (url: string, init?: RequestInit) => {
    calls.push(`${init?.method ?? "GET"} ${url}`);
    const ok = (data: unknown, status = 200) => new Response(JSON.stringify({ success: true, data }), { status });
    if (url.endsWith(`/sessions/${S}/sources`))
      return ok([{ id: "src1", name: "handbook.md", status: "ready", kind: "markdown", size: 1, tokens: 1, fragments: 3, title: null, revisionCount: 2,
        connection: { id: "c", connector: "upload", schedule: "manual", status: "active", syncedRevision: { id: "rev1", number: 1 }, latestRevision: { id: "rev2", number: 2, status: "ingested" },
          lastCheckedAt: null, lastError: null, secretsSet: [] } }]);
    if (url.endsWith("/sources/src1/revisions") && init?.method === "POST") {
      if (uploadStatus === 422) return new Response(JSON.stringify({ success: false, message: "That file type is not allowed." }), { status: 422 });
      return ok({ unchanged: uploadStatus === 200, revision: rev(2, { counts }) }, uploadStatus);
    }
    if (url.endsWith("/sources/src1/revisions")) return ok([rev(2, { counts }), rev(1)]);
    if (url.endsWith("/revisions/rev2/changes")) return ok({ from: rev(1), to: rev(2), counts, changes: [row(1, "minor"), row(2, "trivial")] });
    if (url.endsWith("/revisions/rev1/changes")) return ok({ from: null, to: rev(1), counts: null, changes: [] });
    return new Response("{}", { status: 404 });
  });
  return calls;
}

async function mount(): Promise<HTMLElement> {
  document.body.innerHTML = `<div data-studio-sources data-session="${S}"><div data-sources></div></div><div data-announcer></div>`;
  vi.resetModules();
  const { mountSources } = await import("../../src/studio/sources.ts");
  const root = document.querySelector<HTMLElement>("[data-studio-sources]")!;
  mountSources(root);
  await vi.waitFor(() => expect(root.querySelector("[data-changes] article"), root.innerHTML).not.toBeNull());
  return root;
}

afterEach(() => vi.unstubAllGlobals());

describe("sources page island", () => {
  it("renders the card, the timeline and the changes with cosmetic ones hidden", async () => {
    stubApi();
    const root = await mount();
    expect(root.textContent).toContain("New version available");
    expect(root.textContent).toContain("1 changed, 1 added");
    expect(root.querySelectorAll("[data-changes] article")).toHaveLength(1);
    expect(root.querySelector("[data-changes] del")?.textContent).toContain("removed:");
    const toggle = [...root.querySelectorAll("button")].find((b) => b.textContent === "Show 1 cosmetic change")!;
    toggle.click();
    expect(root.querySelectorAll("[data-changes] article")).toHaveLength(2);
    expect(toggle.getAttribute("aria-pressed")).toBe("true");
    expect(toggle.textContent).toBe("Hide 1 cosmetic change");
  });

  it("shows the empty state for the first revision", async () => {
    stubApi();
    const root = await mount();
    [...root.querySelectorAll("button")].find((b) => b.textContent?.startsWith("Revision 1"))!.click();
    await vi.waitFor(() => expect(root.querySelector(".st-empty")).not.toBeNull());
    expect(root.querySelector(".st-empty")?.textContent).toBe("No source changes yet. New versions appear here when you upload them.");
  });

  it("uploads a file and reports the new revision, the same file and a rejection", async () => {
    const calls = stubApi(201);
    const root = await mount();
    const input = root.querySelector<HTMLInputElement>("[data-file]")!;
    const pick = (name: string) => {
      Object.defineProperty(input, "files", { value: [new File(["# x"], name)], configurable: true });
      input.dispatchEvent(new Event("change"));
    };
    pick("handbook.md");
    await vi.waitFor(() => expect(root.querySelector(".st-upload-status")?.textContent).toContain("Revision 2 added: 1 changed, 1 added."));
    expect(calls.some((c) => c.startsWith("POST ") && c.endsWith("/living-course/sources/src1/revisions"))).toBe(true);

    stubApi(200);
    pick("handbook.md");
    await vi.waitFor(() => expect(root.querySelector(".st-upload-status")?.textContent).toContain("already the latest revision"));

    stubApi(422);
    pick("notes.exe");
    await vi.waitFor(() => expect(root.querySelector("form [role=alert]")?.textContent).toBe("That file type is not allowed."));
  });
});
