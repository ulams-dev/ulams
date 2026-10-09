import { existsSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { validateA2uiMessage, validateSurfaceContent } from "../src/builder/a2ui-validate.ts";
import { BUILDER_CATALOGUE_ID } from "../src/builder/catalogue.ts";
import { builderFixtures } from "./builder-fixtures.ts";

const vendor = fileURLToPath(new URL("../vendor/a2ui/v0.9/", import.meta.url));

/** The envelope EventLog::surface() emits, for one surface of the given kind. */
function envelope(kind: string, id: string, root: Record<string, unknown>, withData = false) {
  const messages: unknown[] = [
    { version: "v0.9", createSurface: { surfaceId: id, catalogId: BUILDER_CATALOGUE_ID } },
    { version: "v0.9", updateComponents: { surfaceId: id, components: [{ id: "root", ...root }] } },
  ];
  if (withData) messages.push({ version: "v0.9", updateDataModel: { surfaceId: id, path: "/", value: {} } });
  return { surfaceId: id, kind, messages };
}

// One surface per kind the API issues (Surfaces.php): source, interview, outline, progress, apply, patch.
const surfaces = {
  source: envelope("source", "source-1", { component: "SourceCard", ...builderFixtures.SourceCard }),
  interview: envelope("interview", "interview-1", { component: "ChoiceChips", ...builderFixtures.ChoiceChips }, true),
  outline: envelope("outline", "outline-1", { component: "OutlineDiff", ...builderFixtures.OutlineDiff }),
  progress: envelope("progress", "progress-1", { component: "GenerationProgress", ...builderFixtures.GenerationProgress }),
  apply: envelope("apply", "apply-1", { component: "ApplySummary", ...builderFixtures.ApplySummary }),
  patch: envelope("patch", "patch-1", { component: "DiffView", ...builderFixtures.DiffView }),
};

describe("vendored A2UI v0.9 schemas", () => {
  it("keep their licence, notice and provenance", () => {
    for (const file of ["LICENSE", "NOTICE", "server_to_client.json", "common_types.json", "client_to_server.json"]) {
      expect(existsSync(`${vendor}${file}`), file).toBe(true);
    }
    expect(readFileSync(`${vendor}LICENSE`, "utf8")).toContain("Apache License");
    expect(readFileSync(`${vendor}NOTICE`, "utf8")).toMatch(/Commit:\s+[0-9a-f]{40}/);
  });
});

describe("a2ui-surface envelope validation", () => {
  it.each(Object.entries(surfaces))("accepts the %s surface", (_kind, content) => {
    expect(validateSurfaceContent(content)).toEqual([]);
  });

  it("accepts deleteSurface", () => {
    expect(validateA2uiMessage({ version: "v0.9", deleteSurface: { surfaceId: "s" } })).toEqual([]);
  });

  it("rejects a wrong version, a missing catalogId and a component without an id", () => {
    expect(validateA2uiMessage({ version: "v0.8", createSurface: { surfaceId: "s", catalogId: "c" } })).not.toEqual([]);
    expect(validateA2uiMessage({ version: "v0.9", createSurface: { surfaceId: "s" } }).map((i) => i.path)).toContain("/createSurface/catalogId");
    expect(validateA2uiMessage({ version: "v0.9", updateComponents: { surfaceId: "s", components: [{ component: "Text" }] } })).not.toEqual([]);
  });

  it("rejects empty component lists, unknown keys and non-messages", () => {
    expect(validateA2uiMessage({ version: "v0.9", updateComponents: { surfaceId: "s", components: [] } })).not.toEqual([]);
    expect(validateA2uiMessage({ version: "v0.9", deleteSurface: { surfaceId: "s", extra: 1 } })).not.toEqual([]);
    expect(validateA2uiMessage({ hello: "world" })).not.toEqual([]);
    expect(validateSurfaceContent({ surfaceId: "s" })).not.toEqual([]);
  });
});
