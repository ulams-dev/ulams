import { describe, expect, it } from "vitest";
import { fakeLms } from "../fake-lms.ts";
import { runCli } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };
const MANIFEST = `
apiVersion: ulams.dev/v1
kind: Course
metadata: { key: k8s }
spec:
  title: Kubernetes 101
  status: draft
  lessons:
    - title: Install
      topics:
        - { type: richtext, title: Intro, markdown: "@intro.md" }
        - { type: oembed, title: Video, url: "https://youtu.be/x" }
---
kind: Group
spec: { name: Sales }
---
kind: Setting
spec: { group: global, key: companyName, value: Acme }
---
kind: Access
spec: { course: Kubernetes 101, users: [student1@x.test], groups: [Sales] }
`;
const files = { "m.yaml": MANIFEST, "intro.md": "# Intro\n\nHello" };

describe("apply", () => {
  it("--dry-run plans every change and writes nothing; --exit-code gives 13", async () => {
    const lms = fakeLms();
    const r = await runCli(["apply", "-f", "m.yaml", "--dry-run", "--exit-code", "--json"], { env, routes: lms.routes, files });
    expect(r.code).toBe(13);
    expect(lms.db.writes).toBe(0);
    const results = (r.json().data as { results: Array<{ kind: string; action: string }> }).results;
    expect(results.map((x) => `${x.kind}:${x.action}`)).toEqual(["Course:created", "Group:created", "Setting:created", "Access:failed"]);
  });

  it("creates everything in order, then a second run performs zero writes", async () => {
    const lms = fakeLms();
    // The Access document refers to the course and group created by earlier documents.
    const first = await runCli(["apply", "-f", "m.yaml", "--json"], { env, routes: lms.routes, files });
    expect(first.code).toBe(0);
    const actions = (first.json().data as Array<{ kind: string; action: string }>).map((x) => `${x.kind}:${x.action}`);
    expect(actions).toEqual(["Course:created", "Group:created", "Setting:created", "Access:updated"]);
    expect(lms.db.courses).toHaveLength(1);
    expect(lms.db.topics.map((t) => t.title)).toEqual(["Intro", "Video"]);
    expect((lms.db.topics[0]?.topicable as { value: string }).value).toBe("<h1>Intro</h1>\n<p>Hello</p>");
    const writes = lms.db.writes;
    const second = await runCli(["apply", "-f", "m.yaml", "--json"], { env, routes: lms.routes, files });
    expect((second.json().data as Array<{ action: string }>).map((x) => x.action)).toEqual(["unchanged", "unchanged", "unchanged", "unchanged"]);
    expect(lms.db.writes).toBe(writes);
    const dry = await runCli(["apply", "-f", "m.yaml", "--dry-run", "--exit-code", "--json"], { env, routes: lms.routes, files });
    expect(dry.code).toBe(0);
  });

  it("updates only what changed", async () => {
    const lms = fakeLms();
    await runCli(["apply", "-f", "m.yaml", "--json"], { env, routes: lms.routes, files });
    const changed = { ...files, "intro.md": "# Intro\n\nChanged" };
    const r = await runCli(["apply", "-f", "m.yaml", "--json"], { env, routes: lms.routes, files: changed });
    const course = (r.json().data as Array<{ action: string; children: Array<{ children: Array<{ key: string; action: string }> }> }>)[0]!;
    expect(course.action).toBe("updated");
    expect(course.children[0]?.children.map((c) => c.action)).toEqual(["updated", "unchanged"]);
  });

  it("rejects an unknown kind or apiVersion with exit 2 before any request", async () => {
    const lms = fakeLms();
    const bad = await runCli(["apply", "-f", "b.yaml", "--json"], { env, routes: lms.routes, files: { "b.yaml": "kind: Spaceship\nspec: {}\n" } });
    expect(bad.code).toBe(2);
    const ver = await runCli(["apply", "-f", "b.yaml", "--json"], { env, routes: lms.routes, files: { "b.yaml": "apiVersion: x/v9\nkind: Group\nspec: {name: a}\n" } });
    expect(ver.code).toBe(2);
    expect(lms.db.writes).toBe(0);
  });

  it("--prune needs --yes and removes lessons missing from the manifest", async () => {
    const lms = fakeLms();
    await runCli(["apply", "-f", "m.yaml", "--json"], { env, routes: lms.routes, files });
    const slim = { "m.yaml": "kind: Course\nspec:\n  title: Kubernetes 101\n  status: draft\n  lessons: []\n" };
    expect((await runCli(["apply", "-f", "m.yaml", "--prune", "--json"], { env, routes: lms.routes, files: slim })).code).toBe(11);
  });

  it("get Course exports a manifest that applies back unchanged", async () => {
    const lms = fakeLms();
    await runCli(["apply", "-f", "m.yaml", "--json"], { env, routes: lms.routes, files });
    const out = await runCli(["get", "Course", "Kubernetes 101", "--output", "yaml"], { env, routes: lms.routes });
    expect(out.stdout).toContain("title: Kubernetes 101");
    expect(out.stdout).toContain("title: Install");
  });
});
