import { describe, expect, it } from "vitest";
import { Sink } from "../../src/course/issues.ts";
import { normalizeMarkdown, parseLessonFile, serializeDirective, serializeLesson, type DirectiveNode, type LooseNode } from "../../src/course/lesson-file.ts";

const ID = "01j9z8aaaaaaaaaaaaaaaaaaaa";
const ID2 = "01j9z9bbbbbbbbbbbbbbbbbbbb";
const FRG = "frg_abcdefabcdef";
const FM = "---\ntitle: Install\nminutes: 5\n---\n";

function parse(text: string) {
  const sink = new Sink();
  const src = parseLessonFile("lesson.md", text, sink);
  return { src, sink, codes: sink.issues.map((i) => `${i.code}@${i.line}`), nodes: src.nodes };
}
const directive = (n: unknown) => n as DirectiveNode;

describe("lesson grammar", () => {
  it("reads front matter and a directive with every attribute", () => {
    const { src, sink, nodes } = parse(`${FM}\n:::callout{#${ID} cite="${FRG},frg_ghijklghijkl" origin=author objectives="${ID2}"}\nHello\n:::\n`);
    expect(sink.issues).toEqual([]);
    expect(src.frontMatter?.data).toEqual({ title: "Install", minutes: 5 });
    expect(directive(nodes[0])).toMatchObject({ kind: "callout", id: ID, cite: [FRG, "frg_ghijklghijkl"], origin: "author", objectives: [ID2], markdown: "Hello", line: 6 });
  });

  it("accepts a directive without attributes, bare and quoted values, and single or many ids", () => {
    const { sink, nodes } = parse(`${FM}\n:::paragraph\nPlain\n:::\n\n:::code{#${ID} cite=${FRG}}\nx\n:::\n`);
    expect(sink.issues).toEqual([]);
    expect(directive(nodes[0])).toMatchObject({ kind: "paragraph", markdown: "Plain" });
    expect(directive(nodes[1])).toMatchObject({ id: ID, cite: [FRG] });
  });

  it("keeps ::: lines inside a backtick or tilde fence as content", () => {
    const { sink, nodes } = parse(`${FM}\n:::code\n\`\`\`text\n:::\nstill code\n\`\`\`\n:::\n\n:::code\n~~~\n:::\n~~~\n:::\n`);
    expect(sink.issues).toEqual([]);
    expect(directive(nodes[0]).markdown).toBe("```text\n:::\nstill code\n```");
    expect(directive(nodes[1]).markdown).toBe("~~~\n:::\n~~~");
  });

  it("a longer fence holds ::: lines", () => {
    const { sink, nodes } = parse(`${FM}\n::::example\nA\n:::\nB\n:::paragraph\nC\n::::\n`);
    expect(sink.issues).toEqual([]);
    expect(directive(nodes[0]).markdown).toBe("A\n:::\nB\n:::paragraph\nC");
  });

  it("handles CRLF line ends and a byte order mark", () => {
    const { sink, nodes, src } = parse(`\uFEFF${FM.replace(/\n/g, "\r\n")}\r\n:::paragraph\r\nA\r\nB\r\n:::\r\n`);
    expect(sink.issues).toEqual([]);
    expect(src.frontMatter?.data).toEqual({ title: "Install", minutes: 5 });
    expect(directive(nodes[0]).markdown).toBe("A\nB");
  });

  it("an empty lesson (front matter only) has no blocks", () => {
    const { sink, nodes } = parse(FM);
    expect(sink.issues).toEqual([]);
    expect(nodes).toEqual([]);
  });

  it("splits loose Markdown at blank lines, keeping fenced code whole", () => {
    const { nodes, sink } = parse(`${FM}\nFirst paragraph\nsecond line\n\n\`\`\`bash\nkubectl get pods\n\nkubectl get nodes\n\`\`\`\n\nLast\n`);
    expect(sink.issues.map((i) => i.code)).toEqual([]);
    expect((nodes as LooseNode[]).map((n) => [n.type, n.markdown, n.line])).toEqual([
      ["loose", "First paragraph\nsecond line", 6],
      ["loose", "```bash\nkubectl get pods\n\nkubectl get nodes\n```", 9],
      ["loose", "Last", 15],
    ]);
  });

  it("loose Markdown between directives keeps its place", () => {
    const { nodes } = parse(`${FM}\n:::paragraph\nA\n:::\n\nbetween\n\n:::paragraph\nB\n:::\n`);
    expect(nodes.map((n) => n.type)).toEqual(["directive", "loose", "directive"]);
  });

  it("a code fence in loose Markdown can contain ::: lines", () => {
    const { nodes, sink } = parse(`${FM}\n\`\`\`\n:::paragraph\n\`\`\`\n`);
    expect(sink.issues).toEqual([]);
    expect(nodes).toHaveLength(1);
  });

  describe("errors, with their lines", () => {
    it("missing front matter", () => expect(parse("Just text\n").codes).toContain("FRONT_MATTER_MISSING@1"));
    it("unclosed front matter", () => expect(parse("---\ntitle: x\n").codes).toContain("FRONT_MATTER_UNCLOSED@1"));
    it("YAML syntax error in front matter, at the file line", () => {
      const { sink } = parse("---\ntitle: [unclosed\nminutes: 5\n---\n");
      const issue = sink.issues.find((i) => i.code === "YAML_SYNTAX");
      expect(issue?.line).toBeGreaterThanOrEqual(2);
    });
    it("unclosed directive reports the opening line", () => expect(parse(`${FM}\n:::paragraph\nnever closed\n`).codes).toEqual(["DIRECTIVE_UNCLOSED@6"]));
    it("stray closer", () => expect(parse(`${FM}\n:::\n`).codes).toEqual(["DIRECTIVE_STRAY_CLOSER@6"]));
    it("unknown kind", () => expect(parse(`${FM}\n:::banner\nx\n:::\n`).codes).toEqual(["DIRECTIVE_KIND_UNKNOWN@6"]));
    it("unknown attribute, at its column", () => {
      const { sink } = parse(`${FM}\n:::callout{variant=info}\nx\n:::\n`);
      expect(sink.issues).toMatchObject([{ code: "DIRECTIVE_ATTR_UNKNOWN", line: 6, column: 12 }]);
    });
    it("invalid id", () => expect(parse(`${FM}\n:::paragraph{#NOPE}\nx\n:::\n`).codes).toEqual(["DIRECTIVE_ATTR_INVALID@6"]));
    it("invalid fragment id", () => expect(parse(`${FM}\n:::paragraph{cite="frg_short"}\nx\n:::\n`).codes).toEqual(["DIRECTIVE_ATTR_INVALID@6"]));
    it("invalid origin", () => expect(parse(`${FM}\n:::paragraph{origin=robot}\nx\n:::\n`).codes).toEqual(["DIRECTIVE_ATTR_INVALID@6"]));
    it("attribute given twice", () => expect(parse(`${FM}\n:::paragraph{origin=ai origin=author}\nx\n:::\n`).codes).toEqual(["DIRECTIVE_ATTR_INVALID@6"]));
    it("a directive line that is not one", () => expect(parse(`${FM}\n::: paragraph\nx\n:::\n`).codes.sort()).toEqual(["DIRECTIVE_STRAY_CLOSER@8", "DIRECTIVE_SYNTAX@6"]));
    it("unterminated attributes", () => expect(parse(`${FM}\n:::paragraph{#${ID}\nx\n:::\n`).codes).toContain("DIRECTIVE_SYNTAX@6"));
    it("empty directive", () => expect(parse(`${FM}\n:::paragraph\n\n:::\n`).codes).toEqual(["DIRECTIVE_EMPTY@6"]));
    it("a directive opened inside another", () => expect(parse(`${FM}\n:::paragraph\nA\n:::paragraph\nB\n:::\n`).codes).toContain("DIRECTIVE_NESTED@8"));
  });
});

describe("serialization", () => {
  const roundTrip = (markdown: string) => {
    const text = serializeLesson("title: T\nminutes: 1\n", [serializeDirective({ kind: "paragraph", id: ID, markdown })]);
    const { sink, nodes } = parse(text);
    return { issues: sink.issues, markdown: directive(nodes[0]).markdown };
  };

  it("picks a fence longer than any directive-like line in the content", () => {
    expect(serializeDirective({ kind: "paragraph", markdown: "a\n:::\nb" }).split("\n")[0]).toBe("::::paragraph");
    expect(serializeDirective({ kind: "paragraph", markdown: "a\n::::\nb" }).split("\n")[0]).toBe(":::::paragraph");
    expect(serializeDirective({ kind: "paragraph", markdown: "plain" }).split("\n")[0]).toBe(":::paragraph");
    expect(serializeDirective({ kind: "paragraph", markdown: "```\n:::\n```" }).split("\n")[0]).toBe(":::paragraph");
  });

  it.each(["a\n:::\nb", "x\n:::paragraph{#abc}\ny", "```\n:::\n```", "::::\n:::\n::::", "- :::\n- b", "  :::  \nindented", "text\n\n\n\nafter four blank lines"])("round-trips %j", (markdown) => {
    const r = roundTrip(markdown);
    expect(r.issues).toEqual([]);
    expect(r.markdown).toBe(normalizeMarkdown(markdown));
  });

  it("writes attributes in one order: id, cite, origin, objectives", () => {
    expect(serializeDirective({ kind: "code", id: ID, cite: [FRG], origin: "author", objectives: [ID2], markdown: "x" })).toBe(`:::code{#${ID} cite="${FRG}" origin=author objectives="${ID2}"}\nx\n:::`);
  });

  it("normalizes block text", () => {
    expect(normalizeMarkdown("\n\n  first\r\nsecond  \n\n")).toBe("  first\nsecond");
  });
});
