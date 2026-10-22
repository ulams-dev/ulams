import { existsSync, readFileSync, readdirSync, writeFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";
import { runCli } from "../helpers.ts";
import { example, fixtures, tempCopy } from "./helpers.ts";

/* End to end through the real command line parser and the real file system; nothing here has a server:
   there are no routes and no ULAMS_URL, and a request would fail the test. */

const offline = { env: {}, routes: {} };

const lines = (stdout: string) => stdout.trim().split("\n");

describe("ulams course validate (offline)", () => {
  it("prints file:line:column issues for the example and exits 0 (warnings only)", async () => {
    const r = await runCli(["course", "validate", example], offline);
    expect(r.code).toBe(0);
    expect(r.requests).toHaveLength(0);
    expect(r.stdout).toMatch(/^modules\/01-basics\/01-install\.md:\d+:1 {2}warning UNCITED_AUTHOR_BLOCK /m);
    expect(r.stdout).toContain("0 errors, 4 warnings, 0 notes in 10 files");
    r.cleanup();
  });

  it("exits 7 with errors, listing every issue with file, line and column", async () => {
    const r = await runCli(["course", "validate", join(fixtures, "broken-semantic")], offline);
    expect(r.code).toBe(7);
    expect(r.stdout).toContain("modules/01-a/01-first.md:19:1  error   MARKUP_HTML");
    expect(r.stdout).toContain("modules/02-b/module.yaml:3:1  error   KEY_DUPLICATE");
    expect(r.stdout).toMatch(/15 errors, 1 warning, 0 notes in 9 files/);
    r.cleanup();
  });

  it("--strict turns warnings into a failure", async () => {
    const ok = await runCli(["course", "validate", example], offline);
    const strict = await runCli(["course", "validate", example, "--strict"], offline);
    expect([ok.code, strict.code]).toEqual([0, 7]);
    ok.cleanup();
    strict.cleanup();
  });

  it("--json is the usual envelope: success with the issues, or an error envelope with the list", async () => {
    const ok = await runCli(["course", "validate", example, "--json"], offline);
    expect(ok.code).toBe(0);
    expect(ok.json()).toMatchObject({ ok: true, command: "course.validate", data: { valid: true, summary: { errors: 0, warnings: 4, infos: 0 }, files: 10 } });

    const bad = await runCli(["course", "validate", join(fixtures, "broken-files"), "--json"], offline);
    expect(bad.code).toBe(7);
    const body = bad.json() as { ok: boolean; error: { code: string; details: { valid: boolean; issues: Array<{ file: string; line: number; column: number; code: string }> } } };
    expect(body.ok).toBe(false);
    expect(body.error.code).toBe("VALIDATION_FAILED");
    expect(body.error.details.issues[0]).toMatchObject({ file: expect.any(String), line: expect.any(Number), column: expect.any(Number), code: expect.any(String) });
    ok.cleanup();
    bad.cleanup();
  });

  it("--format sarif prints SARIF on stdout only, and exits 7 on errors", async () => {
    const r = await runCli(["course", "validate", join(fixtures, "broken-semantic"), "--format", "sarif"], offline);
    expect(r.code).toBe(7);
    const sarif = JSON.parse(r.stdout) as { version: string; runs: Array<{ results: unknown[] }> };
    expect(sarif.version).toBe("2.1.0");
    expect(sarif.runs[0]?.results).toHaveLength(16);
    expect(r.stderr).toBe("");
    r.cleanup();
  });

  it("--format sarif --out writes the file and still reports on the terminal", async () => {
    const t = tempCopy(example);
    const file = join(t.dir, "out.sarif");
    const r = await runCli(["course", "validate", t.dir, "--format", "sarif", "--out", file], offline);
    expect(r.code).toBe(0);
    expect(JSON.parse(readFileSync(file, "utf8")).runs[0].results).toHaveLength(4);
    expect(r.stdout).toContain("0 errors, 4 warnings");
    t.cleanup();
    r.cleanup();
  });

  it("a path that is not a course folder is a usage error, not a crash", async () => {
    const missing = await runCli(["course", "validate", join(fixtures, "nope")], offline);
    expect(missing.code).toBe(5);
    expect(missing.stdout).toContain("does not exist");
    const t = tempCopy(join(fixtures, "messy", "assets"));
    const empty = await runCli(["course", "validate", t.dir], offline);
    expect(empty.code).toBe(7);
    expect(empty.stdout).toContain("FOLDER_NOT_COURSE");
    t.cleanup();
    missing.cleanup();
    empty.cleanup();
  });

  it("defaults to the current directory", async () => {
    const cwd = process.cwd();
    process.chdir(example);
    try {
      const r = await runCli(["course", "validate", "--json"], offline);
      expect(r.code).toBe(0);
      r.cleanup();
    } finally {
      process.chdir(cwd);
    }
  });
});

describe("ulams course fmt (offline)", () => {
  it("formats a messy folder on disk, then has nothing left to do", async () => {
    const t = tempCopy(join(fixtures, "messy"));
    const check = await runCli(["course", "fmt", t.dir, "--check", "--json"], offline);
    expect(check.code).toBe(13);
    expect((check.json().data as { changed: string[] }).changed).toContain("modules/01-intro/02-install.md");
    expect(existsSync(join(t.dir, "modules/2-intro"))).toBe(true);

    const run = await runCli(["course", "fmt", t.dir, "--json"], offline);
    expect(run.code).toBe(0);
    expect(existsSync(join(t.dir, "modules/2-intro"))).toBe(false);
    expect(readdirSync(join(t.dir, "modules")).sort()).toEqual(["01-intro", "02-setup"]);
    expect(readdirSync(join(t.dir, "modules/01-intro")).sort()).toEqual(["01-pods.md", "02-install.md", "02-install.quiz.yaml", "module.yaml"]);
    const lesson = readFileSync(join(t.dir, "modules/01-intro/02-install.md"), "utf8");
    expect(lesson).toMatch(/^---\nid: [0-9a-z]{26}\ntitle: Install kubectl\n/);
    expect(lesson).toContain("origin=author}\nLoose first paragraph\nsecond line of it\n:::");
    expect(lesson).not.toContain("\r");

    const again = await runCli(["course", "fmt", t.dir, "--check", "--json"], offline);
    expect(again.code).toBe(0);
    expect((again.json().data as { changed: string[] }).changed).toEqual([]);

    const validate = await runCli(["course", "validate", t.dir, "--json"], offline);
    const issues = (validate.json() as { error: { details: { issues: Array<{ code: string; severity: string }> } } }).error.details.issues;
    expect(issues.filter((i) => i.severity === "error").map((i) => i.code)).toEqual(["CITATION_MISSING"]);
    for (const r of [check, run, again, validate]) r.cleanup();
    t.cleanup();
  });

  it("validate --fix repairs what is mechanical and reports what is left", async () => {
    const t = tempCopy(join(fixtures, "messy"));
    const r = await runCli(["course", "validate", t.dir, "--fix"], offline);
    expect(r.code).toBe(7);
    expect(r.stdout).toContain("CITATION_MISSING");
    expect(r.stdout).not.toContain("ID_MISSING");
    expect(existsSync(join(t.dir, "modules/01-intro"))).toBe(true);
    t.cleanup();
    r.cleanup();
  });

  it("refuses to format a folder with syntax errors and writes nothing", async () => {
    const t = tempCopy(join(fixtures, "broken-files"));
    const before = readFileSync(join(t.dir, "course.yaml"), "utf8");
    const r = await runCli(["course", "fmt", t.dir], offline);
    expect(r.code).toBe(7);
    expect(r.stdout).toContain("DIRECTIVE_UNCLOSED");
    expect(readFileSync(join(t.dir, "course.yaml"), "utf8")).toBe(before);
    t.cleanup();
    r.cleanup();
  });

  it("leaves the example alone", async () => {
    const r = await runCli(["course", "fmt", example, "--check", "--json"], offline);
    expect(r.code).toBe(0);
    r.cleanup();
  });

  it("keeps files it does not know about", async () => {
    const t = tempCopy(join(fixtures, "messy"));
    writeFileSync(join(t.dir, "modules/2-intro/notes.txt"), "keep me");
    const r = await runCli(["course", "fmt", t.dir], offline);
    expect(r.code).toBe(0);
    expect(readFileSync(join(t.dir, "modules/01-intro/notes.txt"), "utf8")).toBe("keep me");
    t.cleanup();
    r.cleanup();
  });
});

describe("registration", () => {
  it("both commands are local and not offered to MCP", async () => {
    const r = await runCli(["schema", "--json"], offline);
    const commands = (r.json().data as { commands: Array<{ id: string; kind: string; mcp: { expose: boolean } }> }).commands.filter((c) => c.id === "course.validate" || c.id === "course.fmt");
    expect(commands.map((c) => [c.id, c.kind, c.mcp.expose])).toEqual([
      ["course.fmt", "local", false],
      ["course.validate", "local", false],
    ]);
    r.cleanup();
  });

  it("explains itself with --help", async () => {
    const r = await runCli(["course", "validate", "--help"], offline);
    expect(r.code).toBe(0);
    expect(lines(r.stdout).join("\n")).toContain("Check a course folder offline");
    r.cleanup();
  });
});
