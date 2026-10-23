import { describe, expect, it } from "vitest";
import type { DashboardPerson, PolicyDetail, PolicySummary } from "@ulams/sdk";
import { POLICIES_LOCALES, POLICIES_STRINGS, fill } from "../../src/studio/policies-i18n.ts";
import { canApproveFirst, canGenerate, canUploadChange, lowersClass, personRows, policiesFormatterFor, policyRows, stageText, versionRows } from "../../src/studio/policies-model.ts";

const summary = (over: Partial<PolicySummary> = {}): PolicySummary => ({ id: "p1", title: "Remote work", ownerUserId: 1, status: "active", minorDueDays: 14, majorDueDays: 30, brief: { totalMinutes: 30, lessonMinutes: 10, language: "en", tone: "professional" }, currentVersion: 1, latestVersion: 2, pendingReview: 2, departments: 2, createdAt: null, ...over });

const version = (number: number, status: string, changeClass: string) => ({ id: `v${number}`, number, status, changeClass, summary: null, fileName: "p.md", fileSha256: "a", fileSize: 1, submittedBy: null, approvedBy: null, approvedAt: null, effectiveAt: null, suggestedClass: null, classReason: null, rejectedBy: null, rejectReason: null, rolledOutAt: null, createdAt: null });

const detail = (over: Record<string, unknown> = {}): PolicyDetail => ({ ...summary(), versions: [version(1, "approved", "initial")], variants: [], ...over }) as PolicyDetail;

describe("policies i18n", () => {
  const keys = (o: unknown, prefix = ""): string[] => Object.entries(o as Record<string, unknown>).flatMap(([k, v]) => (v && typeof v === "object" ? keys(v, `${prefix}${k}.`) : [`${prefix}${k}`]));
  const en = keys(POLICIES_STRINGS.en).sort();

  it.each(POLICIES_LOCALES)("%s has exactly the keys of English and no empty text", (locale) => {
    expect(keys(POLICIES_STRINGS[locale]).sort()).toEqual(en);
    for (const [k, v] of Object.entries(POLICIES_STRINGS[locale]).flatMap(([a, b]) => (typeof b === "object" ? Object.entries(b as Record<string, unknown>).map(([c, d]) => [`${a}.${c}`, d]) : [[a, b]]))) {
      if (typeof v === "string") expect(v.length, k).toBeGreaterThan(0);
    }
  });

  it.each(POLICIES_LOCALES)("%s keeps every placeholder of English", (locale) => {
    const placeholders = (s: string) => [...s.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort();
    const walk = (a: unknown, b: unknown, path: string) => {
      if (typeof a === "string") expect(placeholders(b as string), `${locale} ${path}`).toEqual(placeholders(a));
      else if (a && typeof a === "object") for (const k of Object.keys(a)) walk((a as Record<string, unknown>)[k], (b as Record<string, unknown>)[k], `${path}.${k}`);
    };
    walk(POLICIES_STRINGS.en, POLICIES_STRINGS[locale], "");
  });

  it("fills placeholders and leaves unknown ones", () => {
    expect(fill("Version {n} of {x}", { n: 2 })).toBe("Version 2 of {x}");
  });
});

describe("policies model", () => {
  const f = policiesFormatterFor("en");

  it("describes policies with the version in force and in review", () => {
    const rows = policyRows(f, [summary(), summary({ id: "p2", title: "Laptops", currentVersion: null, pendingReview: null })]);
    expect(rows[0]).toMatchObject({ current: "Version 1", review: "Version 2", departments: "2", openLabel: "Open Remote work" });
    expect(rows[1]).toMatchObject({ current: "Not approved yet", review: "–" });
  });

  it("writes every stage out and keeps an unknown one", () => {
    expect(stageText(f.t, "outline_review")).toBe("Outline ready: review it in the Studio");
    expect(stageText(f.t, "something_new")).toBe("something_new");
  });

  it("builds people rows with exam, versions and certificate state in words", () => {
    const person = { assignmentId: 1, user: { id: 2, name: "Ada Tester", email: "a@x.test" }, department: "Engineering", variantId: "v", courseId: 4, courseVersion: 2, exam: true, status: "completed", source: "policy_change", assignedAt: null, dueAt: "2026-11-20T10:00:00+00:00", completedAt: null, policyVersion: 2, certificate: { id: 9, certificateId: "abc", status: "revoked", expiresAt: null, verifyPath: "/api/certificates/verify/abc" } } as DashboardPerson;
    const [row] = personRows(f, [person, { ...person, assignmentId: 2, user: null, courseVersion: null, policyVersion: null, certificate: null, status: "overdue" }]);
    expect(row).toMatchObject({ name: "Ada Tester", status: "Completed", courseVersion: "Version 2", policyVersion: "Version 2", exam: true });
    expect(row?.certificate).toEqual({ text: "Revoked", verifyPath: "/api/certificates/verify/abc" });
    expect(personRows(f, [{ ...person, user: null, certificate: null, courseVersion: null, policyVersion: null }])[0]).toMatchObject({ name: "–", courseVersion: "–", policyVersion: "–", certificate: null });
  });

  it("lists versions newest first and links only the ones that can be reviewed", () => {
    const rows = versionRows(f, [version(1, "approved", "initial"), version(2, "in_review", "minor"), version(3, "rejected", "major")] as never);
    expect(rows.map((r) => r.number)).toEqual([3, 2, 1]);
    expect(rows.map((r) => r.reviewable)).toEqual([true, true, false]);
    expect(rows[1]).toMatchObject({ change: "Minor", state: "In review", reviewLabel: "Review version 2" });
  });

  it("knows what the owner can do now", () => {
    expect(canApproveFirst(detail({ currentVersion: null, versions: [version(1, "draft", "initial")] }))).toBe(true);
    expect(canApproveFirst(detail())).toBe(false);
    expect(canUploadChange(detail())).toBe(true);
    expect(canUploadChange(detail({ versions: [version(1, "approved", "initial"), version(2, "in_review", "minor")] }))).toBe(false);
    expect(canUploadChange(detail({ currentVersion: null }))).toBe(false);
    expect(canGenerate(detail({ variants: [{ stage: "not_started" }, { stage: "applied" }] }))).toBe(true);
    expect(canGenerate(detail({ variants: [{ stage: "applied" }] }))).toBe(false);
  });

  it("asks for a reason only when the class is lowered below the suggestion", () => {
    expect(lowersClass("major", "minor")).toBe(true);
    expect(lowersClass("major", "major")).toBe(false);
    expect(lowersClass("minor", "minor")).toBe(false);
    expect(lowersClass(undefined, "minor")).toBe(false);
  });

  it("formats in the reader's language", () => {
    expect(policiesFormatterFor("pl").percent(0.5)).toMatch(/50/);
    expect(policiesFormatterFor("en").percent(null)).toBe("–");
    expect(policiesFormatterFor("zh").cost(1_500_000)).toBe("$1.50");
  });
});
