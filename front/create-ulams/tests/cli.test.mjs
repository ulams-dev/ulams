import { describe, expect, it } from "vitest";
import { resolveCli, stackArgs } from "../src/cli.mjs";

const present = (...paths) => (path) => paths.some((p) => path.endsWith(p));
const noPath = () => ({ status: 1 });

describe("resolveCli", () => {
  it("prefers --cli, then ULAMS_CLI", () => {
    const found = resolveCli({ flag: "/a/ulams.mjs", env: { ULAMS_CLI: "/b/ulams.mjs" }, exists: present("/a/ulams.mjs", "/b/ulams.mjs"), run: noPath });
    expect(found).toMatchObject({ command: process.execPath, args: ["/a/ulams.mjs"], source: "--cli" });
    const env = resolveCli({ env: { ULAMS_CLI: "/b/ulams.mjs" }, exists: present("/b/ulams.mjs"), run: noPath });
    expect(env.source).toBe("ULAMS_CLI");
  });

  it("does not fall back when an explicit path is wrong", () => {
    expect(resolveCli({ flag: "/missing.mjs", exists: () => false, run: () => ({ status: 0 }) })).toBeNull();
    expect(resolveCli({ env: { ULAMS_CLI: "/missing.mjs" }, exists: () => false, run: () => ({ status: 0 }) })).toBeNull();
  });

  it("uses the ulams package this one depends on", () => {
    const found = resolveCli({ env: {}, exists: present("dist/ulams.mjs"), run: noPath });
    expect(found?.source).toBe("bundled ulams");
    expect(found?.args[0]).toMatch(/dist\/ulams\.mjs$/);
  });

  it("falls back to ulams on PATH, or gives up", () => {
    expect(resolveCli({ env: {}, exists: () => false, run: () => ({ status: 0 }) })).toEqual({ command: "ulams", args: [], source: "PATH" });
    expect(resolveCli({ env: {}, exists: () => false, run: noPath })).toBeNull();
  });
});

describe("stackArgs", () => {
  it("builds `ulams stack up` from the options and skips what is unset", () => {
    expect(
      stackArgs({
        directory: "/w/acme",
        name: "acme",
        options: { mode: "development", target: "docker", academy: "acme", academyName: "Acme Lab", theme: "coffee", users: 0, httpPort: 9000, adminEmail: "a@b.co", dryRun: true },
      }),
    ).toEqual([
      "stack", "up", "--directory", "/w/acme", "--name", "acme", "--mode", "development", "--target", "docker",
      "--tenant", "acme", "--academy-name", "Acme Lab", "--theme", "coffee", "--users", "0", "--admin-email", "a@b.co", "--http-port", "9000", "--dry-run",
    ]);
  });
});
