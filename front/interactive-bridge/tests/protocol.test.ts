import { readFileSync, readdirSync } from "node:fs";
import { describe, expect, it } from "vitest";
import { MAX_MESSAGE_BYTES, PACKAGE_TYPES, PARENT_TYPES, parseMessage } from "../src/protocol.ts";
import { NONCE, VALID, env } from "./fixtures.ts";
import { validate } from "./schema-validate.ts";

const schemaDir = new URL("../schema/v1/", import.meta.url);
const schema = (t: string) => JSON.parse(readFileSync(new URL(`${t}.json`, schemaDir), "utf8"));

describe("schemas", () => {
  it("has one schema per message type plus the envelope", () => {
    const files = readdirSync(schemaDir).map((f) => f.replace(".json", "")).sort();
    expect(files).toEqual([...PARENT_TYPES, ...PACKAGE_TYPES, "envelope"].sort());
  });
  it.each(Object.keys(VALID))("accepts a valid %s", (t) => {
    expect(validate(schema(t), env(VALID[t]!))).toEqual([]);
    expect(validate(schema("envelope"), env(VALID[t]!))).toEqual([]);
  });
  it("rejects a wrong version and a missing nonce", () => {
    expect(validate(schema("complete"), { "ulams-ix": 2, type: "complete", nonce: NONCE })).not.toEqual([]);
    expect(validate(schema("complete"), { "ulams-ix": 1, type: "complete" })).not.toEqual([]);
  });
});

describe("guards agree with the schemas", () => {
  const dir = (t: string) => ((PARENT_TYPES as readonly string[]).includes(t) ? "toPackage" : "toParent");
  it.each(Object.keys(VALID))("parses %s", (t) => {
    expect(parseMessage(env(VALID[t]!), dir(t) as "toPackage", NONCE)).not.toBeNull();
  });
  const bad: Array<[string, Record<string, unknown>]> = [
    ["init without chrome", { ...env(VALID.init!), chrome: undefined }],
    ["init with a non-token theme key", { ...env(VALID.init!), theme: { color: "red" } }],
    ["goToStep with a bad id", { ...env(VALID.goToStep!), step: "Too Slow" }],
    ["ready with another protocol", { ...env(VALID.ready!), protocol: 2 }],
    ["progress above 1", { ...env(VALID.progress!), value: 1.5 }],
    ["score with max 0", { ...env(VALID.score!), max: 0 }],
    ["event with a non-IRI verb", { ...env(VALID.event!), verb: "interacted" }],
    ["error with a bad code", { ...env(VALID.error!), code: "Bad Code" }],
  ];
  it.each(bad)("rejects %s (and so do the schemas)", (_n, m) => {
    const t = m.type as string;
    expect(parseMessage(m, dir(t) as "toPackage", NONCE)).toBeNull();
    const clean = JSON.parse(JSON.stringify(m));
    expect(validate(schema(t), clean)).not.toEqual([]);
  });
});

describe("init showcase flag", () => {
  const withShowcase = { ...env(VALID.init!), chrome: "none", showcase: true };
  it("is accepted as a boolean by the guard and the schema", () => {
    expect(parseMessage(withShowcase, "toPackage", NONCE)).not.toBeNull();
    expect(validate(schema("init"), JSON.parse(JSON.stringify(withShowcase)))).toEqual([]);
  });
  it("is rejected when it is not a boolean", () => {
    const bad = { ...withShowcase, showcase: "yes" };
    expect(parseMessage(bad, "toPackage", NONCE)).toBeNull();
    expect(validate(schema("init"), bad)).not.toEqual([]);
  });
});

describe("parseMessage", () => {
  it("drops a wrong nonce, an unknown type, a wrong version and junk", () => {
    expect(parseMessage(env(VALID.complete!, "x".repeat(32)), "toParent", NONCE)).toBeNull();
    expect(parseMessage({ "ulams-ix": 1, type: "launchMissiles", nonce: NONCE }, "toParent", NONCE)).toBeNull();
    expect(parseMessage({ ...env(VALID.complete!), "ulams-ix": 2 }, "toParent", NONCE)).toBeNull();
    for (const junk of [null, undefined, 5, "complete", [], {}]) expect(parseMessage(junk, "toParent", NONCE)).toBeNull();
  });
  it("does not accept a message for the wrong direction", () => {
    expect(parseMessage(env(VALID.complete!), "toPackage", NONCE)).toBeNull();
    expect(parseMessage(env(VALID.goToStep!), "toParent", NONCE)).toBeNull();
  });
  it("drops messages over 16 KB", () => {
    const big = { ...env(VALID.event!), result: { response: "a".repeat(MAX_MESSAGE_BYTES) } };
    expect(parseMessage(big, "toParent", NONCE)).toBeNull();
    const ok = { ...env(VALID.setTheme!), theme: Object.fromEntries(Array.from({ length: 64 }, (_, i) => [`--t-${i}`, "x".repeat(190)])) };
    expect(JSON.stringify(ok).length).toBeLessThan(MAX_MESSAGE_BYTES);
    expect(parseMessage(ok, "toPackage", NONCE)).not.toBeNull();
    const huge = { ...ok, theme: { ...ok.theme, extra: "x".repeat(MAX_MESSAGE_BYTES) } };
    expect(parseMessage(huge, "toPackage", NONCE)).toBeNull();
  });
  it("rejects a circular value without throwing", () => {
    const a: Record<string, unknown> = { ...env(VALID.complete!) };
    a.self = a;
    expect(parseMessage(a, "toParent", NONCE)).toBeNull();
  });
  it("accepts any well-formed nonce when none is fixed yet (the first init)", () => {
    expect(parseMessage(env(VALID.init!, "abcdefgh12345678"), "toPackage", null)).not.toBeNull();
  });
});
