import { describe, expect, it } from "vitest";
import { getPointer, prepare, resolveBindings, validateDocument, type UiNode } from "../src/render-core.ts";

const data = { course: { title: "Atlas", lessons: [{ title: "I" }], empty: [] }, "a/b": { "~c": 1 } };

describe("bindings", () => {
  it("resolves JSON pointers, including escaped keys", () => {
    expect(getPointer(data, "/course/title")).toBe("Atlas");
    expect(getPointer(data, "/course/lessons/0/title")).toBe("I");
    expect(getPointer(data, "/a~1b/~0c")).toBe(1);
    expect(getPointer(data, "/nope/x")).toBeUndefined();
    expect(getPointer(data, "")).toBe(data);
  });

  it("replaces bindings deep in props and falls back to $default for empty data", () => {
    const props = {
      title: { $data: "/course/title" },
      list: { $data: "/course/empty", $default: ["x"] },
      cta: { label: "Go", href: { $data: "/missing", $default: "/" } },
      gone: { $data: "/missing" },
    };
    expect(resolveBindings(props, data)).toEqual({ title: "Atlas", list: ["x"], cta: { label: "Go", href: "/" } });
  });
});

describe("prepare", () => {
  it("validates, applies defaults and keeps children of containers", () => {
    const doc: UiNode = {
      component: "Main",
      children: [{ component: "Callout", props: { text: { $data: "/course/title" } } }],
    };
    const prepared = prepare(doc, { data });
    expect(prepared.kind).toBe("component");
    if (prepared.kind !== "component") return;
    expect(prepared.children[0]).toMatchObject({ kind: "component", component: "Callout", props: { text: "Atlas", tone: "tip" } });
  });

  it("falls back to text for unknown components and invalid props, never throws", () => {
    const issues: string[] = [];
    const unknown = prepare({ component: "Marquee", props: { text: "Hello" } }, { onIssue: (c) => issues.push(c) });
    expect(unknown).toMatchObject({ kind: "fallback", text: "Hello" });
    const invalid = prepare({ component: "Callout", props: { text: "Note", tone: "loud" } });
    expect(invalid).toMatchObject({ kind: "fallback", text: "Note" });
    const unsafe = prepare({ component: "CtaBand", props: { title: "T", cta: { label: "x", href: "javascript:alert(1)" } } });
    expect(unsafe.kind).toBe("fallback");
    expect(prepare(null as unknown as UiNode).kind).toBe("fallback");
    expect(issues).toEqual(["Marquee"]);
  });

  it("renders the valid children of an invalid container", () => {
    const prepared = prepare({ component: "Page", props: { theme: "nope", title: "x" }, children: [{ component: "Main" }] });
    expect(prepared.kind).toBe("fallback");
    if (prepared.kind === "fallback") expect(prepared.children[0]?.kind).toBe("component");
  });

  it("guards against runaway depth", () => {
    let node: UiNode = { component: "Callout", props: { text: "deep" } };
    for (let i = 0; i < 20; i++) node = { component: "Stack", children: [node] };
    expect(validateDocument(node).some((p) => p.issues.some((i) => i.message.includes("deeper")))).toBe(true);
  });
});
