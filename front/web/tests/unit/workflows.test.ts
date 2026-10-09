import { describe, expect, it } from "vitest";
import { registry, WORKFLOW_KINDS } from "@ulams/ui/registry";
import { validate } from "@ulams/ui/schema";
import { landingDocs } from "../../src/lib/docs.ts";
import { workflowsData } from "../../src/lib/workflows.ts";

describe("workflows.json", () => {
  const schema = registry.WorkflowShowcase.props;
  it("is valid against the WorkflowShowcase schema", () => {
    const result = validate(schema, { tabs: workflowsData.tabs, footnote: workflowsData.footnote, description: workflowsData.description });
    expect(result.issues).toEqual([]);
  });
  it("has unique keys, known line kinds and one window per tab", () => {
    const keys = workflowsData.tabs.map((t) => t.key);
    expect(new Set(keys).size).toBe(keys.length);
    for (const tab of workflowsData.tabs as Array<{ key: string; lines: Array<{ kind: string; text: string }>; window: string }>) {
      expect(["terminal", "chat", "studio"], tab.key).toContain(tab.window);
      for (const line of tab.lines) {
        expect(WORKFLOW_KINDS as readonly string[], tab.key).toContain(line.kind);
        expect(line.text.length, tab.key).toBeGreaterThan(0);
      }
    }
  });
  it("says the CLI and MCP interfaces are planned", () => {
    expect(workflowsData.footnote).toMatch(/planned interface/i);
    const planned = (workflowsData.tabs as Array<{ status?: string; note?: string }>).filter((t) => t.status === "coming");
    for (const tab of planned) expect(tab.note).toMatch(/planned interface/i);
  });
  it("names Claude Code and Claude as tools to use, without logos or endorsement claims", () => {
    const text = JSON.stringify(landingDocs.platform) + JSON.stringify(workflowsData);
    expect(text).toContain("Claude Code");
    expect(text).toContain("MCP-compatible");
    expect(text).not.toMatch(/official integration|partnership|our partner|<img/i);
    expect(text).toContain("not affiliated");
  });
});
