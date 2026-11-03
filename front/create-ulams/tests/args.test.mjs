import { describe, expect, it } from "vitest";
import { UsageError, parse, projectName } from "../src/args.mjs";

describe("parse", () => {
  it("defaults to a local development academy with demo students", () => {
    const parsed = parse([]);
    expect(parsed.directory).toBe("my-academy");
    expect(parsed.name).toBe("my-academy");
    expect(parsed.options).toMatchObject({ mode: "development", target: "docker", users: 3, theme: "coffee", force: false, dryRun: false });
  });

  it("takes the directory and the options", () => {
    const parsed = parse(["acme-lab", "--academy", "acme", "--academy-name", "Acme Lab", "--theme", "nightsky", "--users", "0", "--http-port", "9000", "--admin-email", "a@b.co", "--force"]);
    expect(parsed.directory).toBe("acme-lab");
    expect(parsed.options).toMatchObject({ academy: "acme", academyName: "Acme Lab", theme: "nightsky", users: 0, httpPort: 9000, adminEmail: "a@b.co", force: true });
  });

  it("production needs a domain and keeps the CLI defaults for seed data", () => {
    expect(() => parse(["--mode", "production"])).toThrow(/--domain/);
    const parsed = parse(["x1", "--mode", "production", "--domain", "learn.example.org"]);
    expect(parsed.options.users).toBeUndefined();
    expect(parsed.options.theme).toBeUndefined();
  });

  it.each([
    [["--mode", "staging"], /--mode/],
    [["--target", "nomad"], /--target/],
    [["--theme", "neon"], /--theme/],
    [["--users", "51"], /--users/],
    [["--users", "-1"], /--users/],
    [["--users", "two"], /--users/],
    [["--http-port", "70000"], /--http-port/],
    [["--academy", "Bad Slug"], /--academy/],
    [["--admin-email", "nope"], /--admin-email/],
    [["--tls", "none"], /--tls/],
    [["a1", "b2"], /one directory/],
    [["--unknown"], /unknown/i],
  ])("rejects %j", (argv, message) => {
    expect(() => parse(argv)).toThrow(UsageError);
    expect(() => parse(argv)).toThrow(message);
  });

  it("recognises help and version without validating the rest", () => {
    expect(parse(["--help"]).help).toBe(true);
    expect(parse(["-v"]).version).toBe(true);
  });
});

describe("projectName", () => {
  it("turns a directory into a Compose project name", () => {
    expect(projectName("My Academy!")).toBe("my-academy");
    expect(projectName("/tmp/work/Acme_Lab/")).toBe("acme-lab");
    expect(projectName("a".repeat(60))).toHaveLength(40);
  });

  it("refuses names that cannot be a project", () => {
    expect(() => projectName("1abc")).toThrow(UsageError);
    expect(() => projectName("..")).toThrow(UsageError);
    expect(() => projectName("x")).toThrow(UsageError);
  });
});
