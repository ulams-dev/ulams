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
  it("marks the shipped CLI and MCP tabs available, without planned-interface wording", () => {
    const tabs = workflowsData.tabs as Array<{ key: string; status?: string; note?: string }>;
    for (const key of ["claude-code", "claude-mcp", "cli"]) expect(tabs.find((t) => t.key === key)?.status, key).toBe("available");
    expect(workflowsData.footnote).toBeUndefined();
    expect(JSON.stringify(workflowsData)).not.toMatch(/planned interface|being built|Phase \d/i);
  });
  it("shows only things that work today: no learner analytics in the MCP example", () => {
    const mcp = JSON.stringify(workflowsData.tabs.find((t) => t.key === "claude-mcp"));
    expect(mcp).toContain("courses_list");
    expect(mcp).toContain("courses_publish");
    expect(mcp).not.toMatch(/analytics|at_risk|stuck|nudge/i);
  });
  it("names Claude Code and Claude as tools to use, without logos or endorsement claims", () => {
    const text = JSON.stringify(landingDocs.platform) + JSON.stringify(workflowsData);
    expect(text).toContain("Claude Code");
    expect(text).toContain("MCP-compatible");
    expect(text).not.toMatch(/official integration|partnership|our partner|<img/i);
    expect(text).toContain("not affiliated");
  });
});
