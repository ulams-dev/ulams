import { readFileSync, readdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { localImages, markup, markupFindings, questionShape } from "../../src/course/checks.ts";

const here = dirname(fileURLToPath(import.meta.url));
const dir = join(here, "../../../../api/packages/course-builder/tests/fixtures/checks");

interface Case {
  name: string;
  where: string;
  input: never;
  expected: string[];
}

describe("checks shared with the server (api/packages/course-builder/tests/fixtures/checks)", () => {
  const files = readdirSync(dir).filter((f) => f.endsWith(".json"));
  it("has fixture files", () => expect(files.sort()).toEqual(["markup.json", "question-shape.json"]));
  for (const file of files) {
    const set = JSON.parse(readFileSync(join(dir, file), "utf8")) as { check: "markup" | "questionShape"; cases: Case[] };
    for (const c of set.cases) {
      it(`${file}: ${c.name}`, () => {
        const actual = set.check === "markup" ? markup(c.input as unknown as string, c.where) : questionShape(c.input, c.where);
        expect(actual).toEqual(c.expected);
      });
    }
  }
});

describe("markup positions", () => {
  it("reports the line where the problem starts, counting lines inside code", () => {
    const text = "one\n```\n<b>in code</b>\n```\nthree <i>x</i>\nfive [a](javascript:1)";
    expect(markupFindings(text, "x").map((f) => [f.code, f.line])).toEqual([
      ["MARKUP_HTML", 4],
      ["MARKUP_SCRIPT_LINK", 5],
    ]);
  });
  it("finds local images outside code only", () => {
    expect(localImages("![a](../../assets/a.png)\n```\n![b](c.png)\n```\n![r](https://e.org/r.png) ![t](t.png \"title\")")).toEqual([
      { path: "../../assets/a.png", line: 0 },
      { path: "t.png", line: 4 },
    ]);
  });
});
