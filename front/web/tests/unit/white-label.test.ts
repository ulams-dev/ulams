/* eslint-disable @typescript-eslint/no-explicit-any */
import { describe, expect, it } from "vitest";
import { registry } from "@ulams/ui/registry";
import { validate } from "@ulams/ui/schema";
import { landingDocs } from "../../src/lib/docs.ts";
import { applyLandingStatus } from "../../src/lib/landing-status.ts";

type Item = { status?: string };
type Node = { component: string; props?: Record<string, any> };

type Tree = { component?: string; children?: Tree[] };
const find = (doc: unknown): Node => {
  const walk = (n: Tree): Node | undefined => (n.component === "WhiteLabelStory" ? (n as Node) : (n.children ?? []).map(walk).find(Boolean));
  return walk(doc as Tree)!;
};
const doc = landingDocs.platform!;
const order = () => (doc.children!.find((c) => c.component === "Main")!.children as Node[]).map((c) => c.props?.id ?? c.component);

describe("white-label section", () => {
  it("is valid against the WhiteLabelStory schema with four steps", () => {
    const node = find(doc);
    expect(validate(registry.WhiteLabelStory.props, node.props).issues).toEqual([]);
    expect(node.props!.steps).toHaveLength(4);
  });
  it("sits after the stories and before the demos and the comparison", () => {
    const ids = order();
    expect(ids.indexOf("agents")).toBeLessThan(ids.indexOf("white-label"));
    expect(ids.indexOf("white-label")).toBeLessThan(ids.indexOf("compare"));
  });
  it("has the two calls to action", () => {
    const p = find(doc).props!;
    expect(p.primaryCta).toEqual({ label: "Try a live demo", href: "#demos" });
    expect(p.secondaryCta.label).toBe("Self-host it");
  });
  it("shows real CLI commands only, no customer logos and no metrics", () => {
    const p = find(doc).props!;
    for (const c of p.brand.commands) expect(c).toMatch(/^ulams (theme set|settings set|api) /);
    const text = JSON.stringify(p);
    expect(text).not.toMatch(/\d\s*%|customers|trusted by/i);
    expect(p.site.host).toBe("acme.ulams.app");
  });
  it("final mode: nothing is marked Coming and no roadmap captions", () => {
    const text = JSON.stringify(find(applyLandingStatus(doc, "final")));
    expect(text).not.toMatch(/"(status|syncStatus)":"coming"|"today"|roadmap/i);
  });
  it("actual mode: step 1 is available, analytics and sync are Coming", () => {
    const p = find(applyLandingStatus(doc, "actual")).props!;
    expect(p.steps[0].status).toBeUndefined();
    expect(p.brand.sources.some((s: Item) => s.status === "coming")).toBe(false);
    expect(p.steps[1].status).toBeUndefined();
    expect(p.steps[2].status).toBeUndefined();
    expect(p.steps[3].status).toBe("coming");
    expect(p.content.syncStatus).toBe("coming");
    expect(p.content.sources.some((s: Item) => s.status === "coming")).toBe(false);
    expect(p.steps[0].today).toMatch(/importer/);
  });
});
