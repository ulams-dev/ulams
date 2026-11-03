import { describe, expect, it, vi } from "vitest";
import { main } from "../src/main.mjs";

function setup(overrides = {}) {
  const out = [];
  const err = [];
  const launch = vi.fn(async () => 0);
  const deps = {
    io: { out: (t) => out.push(t), err: (t) => err.push(t) },
    nodeVersion: "22.12.0",
    cwd: "/work",
    inspectDirectory: () => ({ state: "missing", entries: [] }),
    dockerComposeAvailable: () => true,
    resolveCli: () => ({ command: "node", args: ["/cli/ulams.mjs"], source: "test" }),
    launch,
    version: "9.9.9",
    ...overrides,
  };
  return { deps, out, err, launch };
}

describe("main", () => {
  it("prints help and the version", async () => {
    const { deps, out } = setup();
    expect(await main(["--help"], deps)).toBe(0);
    expect(out.join("\n")).toContain("npx create-ulams [directory]");
    expect(await main(["--version"], deps)).toBe(0);
    expect(out.at(-1)).toBe("9.9.9");
  });

  it("launches the stack with the seed options and tells you how to manage it", async () => {
    const { deps, out, launch } = setup();
    expect(await main(["acme", "--academy", "acme", "--users", "5"], deps)).toBe(0);

    expect(launch).toHaveBeenCalledTimes(1);
    const [command, args] = launch.mock.calls[0];
    expect(command).toBe("node");
    expect(args).toEqual([
      "/cli/ulams.mjs", "stack", "up", "--directory", "/work/acme", "--name", "acme", "--mode", "development", "--target", "docker",
      "--tenant", "acme", "--theme", "coffee", "--users", "5",
    ]);
    expect(out.join("\n")).toContain("npx ulams stack status --directory acme");
  });

  it("passes production options through and leaves seed data to the CLI defaults", async () => {
    const { deps, launch } = setup();
    await main(["prod1", "--mode", "production", "--domain", "learn.example.org", "--tls", "internal", "--image-tag", "v1"], deps);

    const args = launch.mock.calls[0][1];
    expect(args).toEqual(expect.arrayContaining(["--mode", "production", "--domain", "learn.example.org", "--tls", "internal", "--image-tag", "v1"]));
    expect(args).not.toContain("--users");
    expect(args).not.toContain("--theme");
  });

  it("refuses a directory that already has files, unless --force", async () => {
    const { deps, err, launch } = setup({ inspectDirectory: () => ({ state: "not-empty", entries: ["compose.yaml", ".env"] }) });

    expect(await main(["acme"], deps)).toBe(2);
    expect(err.join("\n")).toMatch(/is not empty \(compose\.yaml, \.env\)/);
    expect(launch).not.toHaveBeenCalled();

    expect(await main(["acme", "--force"], deps)).toBe(0);
    expect(launch).toHaveBeenCalledTimes(1);
  });

  it("accepts an empty directory and refuses a file", async () => {
    expect(await main(["acme"], setup({ inspectDirectory: () => ({ state: "empty", entries: [] }) }).deps)).toBe(0);
    const file = setup({ inspectDirectory: () => ({ state: "file", entries: [] }) });
    expect(await main(["acme"], file.deps)).toBe(2);
    expect(file.err.join("\n")).toContain("is a file");
  });

  it("needs a new enough Node", async () => {
    const { deps, err, launch } = setup({ nodeVersion: "20.11.1" });
    expect(await main([], deps)).toBe(1);
    expect(err.join("\n")).toContain("Node 22.12");
    expect(launch).not.toHaveBeenCalled();
  });

  it("needs Docker Compose to start, but not to plan", async () => {
    const { deps, err, launch } = setup({ dockerComposeAvailable: () => false });
    expect(await main([], deps)).toBe(1);
    expect(err.join("\n")).toContain("Docker Compose v2");
    expect(launch).not.toHaveBeenCalled();

    expect(await main(["--dry-run"], deps)).toBe(0);
    expect(launch.mock.calls[0][1]).toContain("--dry-run");
  });

  it("does not check Docker for Kubernetes", async () => {
    const { deps, launch } = setup({ dockerComposeAvailable: () => false });
    expect(await main(["--target", "kubernetes"], deps)).toBe(0);
    expect(launch).toHaveBeenCalled();
  });

  it("says what to do when the ulams CLI cannot be found", async () => {
    const { deps, err, launch } = setup({ resolveCli: () => null });
    expect(await main([], deps)).toBe(1);
    expect(err.join("\n")).toMatch(/npm install -g ulams/);
    expect(launch).not.toHaveBeenCalled();
  });

  it("reports a bad option as a usage error", async () => {
    const { deps, err } = setup();
    expect(await main(["--mode", "nope"], deps)).toBe(2);
    expect(err.join("\n")).toContain("--mode must be");
  });

  it("returns the exit code of a failed launch and explains how to resume", async () => {
    const { deps, err, out } = setup({ launch: vi.fn(async () => 7) });
    expect(await main(["acme"], deps)).toBe(7);
    expect(err.join("\n")).toMatch(/exit code 7[\s\S]*--force to resume/);
    expect(out.join("\n")).not.toContain("is running");
  });
});
