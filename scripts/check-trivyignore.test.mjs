import assert from "node:assert/strict";
import { test } from "node:test";
import { checkIgnoreFile } from "./check-trivyignore.mjs";

const today = new Date("2026-10-21T00:00:00Z");
const entry = (id, statement, expiredAt) =>
  `  - id: ${id}\n${statement === null ? "" : `    statement: ${statement}\n`}${expiredAt === null ? "" : `    expired_at: ${expiredAt}\n`}`;
const file = (...entries) => `vulnerabilities:\n${entries.join("")}`;

test("a complete, current entry passes", () => {
  const r = checkIgnoreFile(file(entry("CVE-1", "reason", "2026-12-01")), today);
  assert.equal(r.entries, 1);
  assert.deepEqual(r.problems, []);
});

test("an entry that expires today still counts, yesterday does not", () => {
  assert.deepEqual(checkIgnoreFile(file(entry("CVE-1", "r", "2026-10-21")), today).problems, []);
  assert.match(checkIgnoreFile(file(entry("CVE-1", "r", "2026-10-20")), today).problems[0], /expired on 2026-10-20/);
});

test("missing statement and missing expiry are reported", () => {
  const r = checkIgnoreFile(file(entry("CVE-1", null, "2026-12-01"), entry("CVE-2", "r", null)), today);
  assert.equal(r.problems.length, 2);
  assert.match(r.problems[0], /CVE-1.*statement/);
  assert.match(r.problems[1], /CVE-2.*expired_at is missing/);
});

test("folded statement text counts as a statement", () => {
  const folded = "  - id: DS-0002\n    statement: >-\n      Because of a reason.\n    expired_at: 2027-01-01\n";
  assert.deepEqual(checkIgnoreFile(`misconfigurations:\n${folded}`, today).problems, []);
  const empty = "  - id: DS-0002\n    statement: >-\n    expired_at: 2027-01-01\n";
  assert.equal(checkIgnoreFile(`misconfigurations:\n${empty}`, today).problems.length, 1);
});

test("bad dates and dates more than a year ahead are reported", () => {
  assert.match(checkIgnoreFile(file(entry("CVE-1", "r", "soon")), today).problems[0], /not a YYYY-MM-DD/);
  assert.match(checkIgnoreFile(file(entry("CVE-1", "r", "2028-01-01")), today).problems[0], /more than a year/);
});

test("the committed file passes", async () => {
  const { readFileSync } = await import("node:fs");
  const text = readFileSync(new URL("../.trivyignore.yaml", import.meta.url), "utf8");
  const r = checkIgnoreFile(text, today);
  assert.deepEqual(r.problems, []);
  assert.ok(r.entries > 100);
});
