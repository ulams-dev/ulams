import { describe, expect, it } from "vitest";
import { isSafeHref, validate, type JsonSchema } from "../src/schema.ts";

const schema: JsonSchema = {
  type: "object",
  required: ["title"],
  properties: {
    title: { type: "string", maxLength: 10 },
    variant: { type: "string", enum: ["a", "b"], default: "a" },
    count: { type: "integer", minimum: 0 },
    link: { type: "string", format: "href" },
    items: { type: "array", maxItems: 2, items: { type: "object", properties: { x: { type: "number" } }, required: ["x"] } },
  },
};

describe("validate", () => {
  it("applies defaults and accepts valid input", () => {
    const r = validate(schema, { title: "Hi", items: [{ x: 1.5 }] });
    expect(r.valid).toBe(true);
    expect(r.value).toEqual({ title: "Hi", variant: "a", items: [{ x: 1.5 }] });
  });

  it("reports type, enum, required, length and unknown props with paths", () => {
    const r = validate(schema, { variant: "c", count: 1.5, title: "x".repeat(11), extra: true, items: [{}, { x: 1 }, { x: 2 }] });
    const messages = r.issues.map((i) => `${i.path} ${i.message}`);
    expect(r.valid).toBe(false);
    expect(messages).toEqual(
      expect.arrayContaining([
        "/variant must be one of \"a\", \"b\"",
        "/count expected integer, got number",
        "/title longer than 10",
        "/extra is not a known prop",
        "/items more than 2 items",
        "/items/0/x is required",
      ])
    );
  });

  it("does not mutate defaults between calls", () => {
    const s: JsonSchema = { type: "object", properties: { list: { type: "array", default: [] } } };
    const a = validate<{ list: unknown[] }>(s, {}).value;
    a.list.push(1);
    expect(validate<{ list: unknown[] }>(s, {}).value.list).toEqual([]);
  });
});

describe("isSafeHref", () => {
  it.each([
    ["/courses/1", true],
    ["#pricing", true],
    ["https://example.com", true],
    ["mailto:a@b.c", true],
    ["javascript:alert(1)", false],
    ["data:text/html,x", false],
    ["//evil.example", false],
    ["", false],
  ])("%s → %s", (href, ok) => expect(isSafeHref(href)).toBe(ok));
});
