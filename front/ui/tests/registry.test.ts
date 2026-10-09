import { readFileSync, existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { catalogueJson, componentNames, registry } from "../src/registry.ts";
import { validate } from "../src/schema.ts";

const root = fileURLToPath(new URL("../src/", import.meta.url));

describe("registry", () => {
  it("has an Astro implementation, mapped in Node.astro, for every component", () => {
    const node = readFileSync(`${root}Node.astro`, "utf8");
    for (const name of componentNames) {
      expect(existsSync(`${root}components/${name}.astro`), name).toBe(true);
      expect(node).toContain(`import ${name} from "./components/${name}.astro"`);
    }
  });

  it("describes every component for the model and validates its own defaults", () => {
    for (const [name, spec] of Object.entries(registry)) {
      expect(spec.description.length, name).toBeGreaterThan(20);
      expect(spec.props.type, name).toBe("object");
      const required = spec.props.required ?? [];
      if (required.length === 0) expect(validate(spec.props, {}).valid, name).toBe(true);
      expect(typeof spec.fallback({}), name).toBe("string");
    }
  });

  it("gives every enum prop a known value set and every section an anchor id", () => {
    for (const [name, spec] of Object.entries(registry)) {
      for (const [prop, schema] of Object.entries(spec.props.properties ?? {})) {
        if (schema.enum) expect(schema.enum.length, `${name}.${prop}`).toBeGreaterThan(0);
        if (schema.enum && schema.default !== undefined) expect(schema.enum).toContain(schema.default);
      }
      if (spec.category !== "structure") expect(spec.props.properties?.id, name).toBeDefined();
    }
  });

  it("serialises to plain JSON for prompts", () => {
    const json = JSON.parse(JSON.stringify(catalogueJson())) as Record<string, { description: string }>;
    expect(Object.keys(json)).toEqual(componentNames);
    expect(json.Hero?.description).toContain("landing");
  });
});
