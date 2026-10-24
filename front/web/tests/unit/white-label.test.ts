import { describe, expect, it } from "vitest";
import { landingDocs } from "../../src/lib/docs.ts";

type Node = { component: string; props?: Record<string, unknown> };

const doc = landingDocs.platform!;
const main = doc.children!.find((node) => node.component === "Main")!;
const sections = main.children as Node[];
const heritage = sections.find((node) => node.component === "HeritageStory")!;

describe("concise platform feature list", () => {
  it("keeps the home page to the product: no heritage story and no separate white-label story", () => {
    expect(heritage).toBeUndefined();
    expect(sections.some((section) => section.component === "WhiteLabelStory")).toBe(false);
  });

  it("keeps demos and developer workflow on Overview, with comparison linked separately", () => {
    const order = sections.map((section) => section.component);
    expect(order.indexOf("Showcase")).toBeLessThan(order.indexOf("WorkflowShowcase"));
    expect(order).not.toContain("ComparisonTable");
    expect((doc.children!.find((node) => node.component === "SiteHeader")!.props!.links as Array<{ href: string }>)[0]!.href).toBe("/comparison/");
  });
});
