// Opt-in end-to-end test against a running local stack (ULAMS_E2E=1 yarn workspace ulams test:e2e).
// Uses the demo admin on the coffee tenant, creates a course and deletes it again.
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { spawnSync } from "node:child_process";
import { existsSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";

const enabled = process.env.ULAMS_E2E === "1";
const URL_ = process.env.ULAMS_E2E_URL ?? "http://coffee.localhost";
const root = resolve(__dirname, "../..");
const dist = resolve(root, "dist/ulams.mjs");
const FIXTURES = resolve(root, "../../api/packages");

let dir = "";
function ulams(args: string[], opts: { profile?: string; stdin?: string } = {}) {
  const res = spawnSync("node", [dist, ...args, "--json", ...(opts.profile ? ["--profile", opts.profile] : [])], {
    encoding: "utf8",
    input: opts.stdin,
    env: { ...process.env, ULAMS_CONFIG_DIR: dir, ULAMS_URL: "", ULAMS_TOKEN: "" },
  });
  const out = res.stdout.trim() ? (JSON.parse(res.stdout.trim().split("\n")[0] as string) as Record<string, any>) : {}; // eslint-disable-line @typescript-eslint/no-explicit-any
  return { code: res.status ?? -1, out, stderr: res.stderr };
}

describe.skipIf(!enabled)("e2e: coffee tenant", () => {
  const created: { course?: number; applied?: number } = {};

  beforeAll(() => {
    if (!existsSync(dist)) spawnSync("npx", ["tsup"], { cwd: root, stdio: "inherit" });
    dir = mkdtempSync(join(tmpdir(), "ulams-e2e-"));
  });

  afterAll(() => {
    for (const id of [created.course, created.applied]) if (id) ulams(["courses", "delete", String(id), "--yes"]);
    rmSync(dir, { recursive: true, force: true });
  });

  it("logs in as the demo admin", () => {
    const r = ulams(["login", "--url", URL_, "--demo", "admin"]);
    expect(r.code).toBe(0);
    expect(r.out.data.user).toMatch(/^admin@/);
    expect(ulams(["whoami"]).out.data.user.roles).toContain("admin");
  });

  it("creates a course with a lesson, a text topic and a quiz, publishes it, enrols a student and deletes it", () => {
    const course = ulams(["courses", "create", "--title", `CLI e2e ${Date.now()}`]);
    expect(course.code).toBe(0);
    created.course = course.out.data.id as number;
    const lesson = ulams(["lessons", "create", "--title", "Lesson 1", "--order", "1", "--course-id", String(created.course)]);
    expect(lesson.code).toBe(0);
    const lessonId = String(lesson.out.data.id);
    writeFileSync(join(dir, "intro.md"), "# Intro\n\nHello from the CLI.\n");
    expect(ulams(["topics", "create-richtext", "--lesson", lessonId, "--title", "Intro", "--markdown", `@${join(dir, "intro.md")}`]).code).toBe(0);
    writeFileSync(join(dir, "quiz.yaml"), "questions:\n  - prompt: Is this a test?\n    options:\n      - { text: Yes, correct: true }\n      - { text: No }\n");
    expect(ulams(["topics", "create-quiz", "--lesson", lessonId, "--title", "Quiz", "--input", `@${join(dir, "quiz.yaml")}`]).code).toBe(0);

    const shown = ulams(["courses", "get", String(created.course)]);
    expect(shown.out.data.lessons[0].topics).toHaveLength(2);

    // dry-run first, then publish (idempotent)
    expect(ulams(["courses", "publish", String(created.course), "--dry-run"]).out.data.dryRun).toBe(true);
    expect(ulams(["courses", "publish", String(created.course)]).out.data.status).toBe("published");
    expect(ulams(["courses", "publish", String(created.course)]).out.data.changed).toBe(false);

    const student = ulams(["users", "list", "--search", "student1"]).out.data[0].id as number;
    expect(ulams(["access", "grant", "--course", String(created.course), "--user", String(student)]).code).toBe(0);
    expect(ulams(["access", "list", "--course", String(created.course)]).out.data.users.map((u: { id: number }) => u.id)).toContain(student);

    // the student sees it
    expect(ulams(["login", "--url", URL_, "--demo", "student", "--profile", "student", "--no-make-default"]).code).toBe(0);
    const mine = ulams(["my", "courses-my"], { profile: "student" });
    expect(mine.code).toBe(0);

    // destructive: refused without --yes, works with it
    expect(ulams(["courses", "delete", String(created.course)]).code).toBe(11);
    expect(ulams(["courses", "delete", String(created.course), "--yes"]).code).toBe(0);
    expect(ulams(["courses", "get", String(created.course)]).code).toBe(5);
    created.course = undefined;
  });

  it("apply is idempotent: the second run performs no writes", () => {
    writeFileSync(join(dir, "a.md"), "# Apply\n\nBody\n");
    const manifest = `kind: Course\nmetadata: { key: e2e }\nspec:\n  title: CLI apply e2e ${process.pid}\n  status: draft\n  lessons:\n    - title: One\n      topics:\n        - { type: richtext, title: Text, markdown: "@${join(dir, "a.md")}" }\n`;
    writeFileSync(join(dir, "m.yaml"), manifest);
    const first = ulams(["apply", "-f", join(dir, "m.yaml")]);
    expect(first.code).toBe(0);
    created.applied = first.out.data[0].id as number;
    const second = ulams(["apply", "-f", join(dir, "m.yaml")]);
    expect(second.out.data[0].action).toBe("unchanged");
    expect(ulams(["apply", "-f", join(dir, "m.yaml"), "--dry-run", "--exit-code"]).code).toBe(0);
  });

  it("uploads a PDF, an audio file, a SCORM package and an H5P package as topics", () => {
    const course = ulams(["courses", "create", "--title", `CLI uploads ${Date.now()}`]).out.data.id as number;
    try {
      const lesson = String(ulams(["lessons", "create", "--title", "U", "--order", "1", "--course-id", String(course)]).out.data.id);
      const mocks = join(FIXTURES, "courses-import-export/tests/mocks");
      expect(ulams(["topics", "create-file", "--lesson", lesson, "--title", "PDF", "--file", join(mocks, "1.pdf")]).code).toBe(0);
      expect(ulams(["topics", "create-file", "--lesson", lesson, "--title", "Audio", "--file", join(mocks, "1.mp3")]).code).toBe(0);
      expect(ulams(["topics", "create-scorm", "--lesson", lesson, "--title", "SCORM", "--package", join(FIXTURES, "scorm/database/mocks/RuntimeBasicCalls_SCORM12.zip")]).code).toBe(0);
      expect(ulams(["topics", "create-h5p", "--lesson", lesson, "--title", "H5P", "--package", join(mocks, "hp5.h5p")]).code).toBe(0);
      const topics = ulams(["courses", "get", String(course)]).out.data.lessons[0].topics as Array<{ topicable_type: string }>;
      expect(topics.map((t) => t.topicable_type.split("\\").pop()).sort()).toEqual(["Audio", "H5P", "PDF", "ScormSco"]);
    } finally {
      ulams(["courses", "delete", String(course), "--yes"]);
    }
  });
});
