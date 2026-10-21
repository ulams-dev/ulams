import { describe, expect, it, vi } from "vitest";
import type { Assignment, Certificate, PathEnrollment, Skill, Tenant } from "@ulams/sdk";
import {
  accountSummary,
  assignmentRows,
  badgeSnippet,
  certificateRow,
  isCourseCertificate,
  loadCourseCertificate,
  loadTeam,
  loadTraining,
  pathRows,
  siteOrigin,
  skillGroups,
  teamFilter,
} from "../../src/lib/training.ts";

const NOW = new Date("2026-10-21T12:00:00Z");
const tenant = { apiUrl: "https://acme.test", slug: "acme" } as unknown as Tenant;

const assignment = (over: Partial<Assignment>): Assignment => ({
  id: 1,
  user: null,
  course: { id: 5, title: "Safety" },
  source: "manual",
  status: "assigned",
  assigned_at: "2026-09-01T00:00:00+00:00",
  due_at: null,
  completed_at: null,
  escalation_level: 0,
  certificate: null,
  ...over,
});
const cert = (over: Partial<Certificate>): Certificate => ({
  id: 9,
  title: "Safety certificate",
  certificate_id: "b3f1c2d4-1111-4222-8333-444455556666",
  assignable_type: "Ulams\\Courses\\Models\\Course",
  assignable_id: 5,
  status: "valid",
  expires_at: null,
  revoked_at: null,
  course_version: 3,
  created_at: "2026-03-01T10:00:00+00:00",
  ...over,
});

describe("required training", () => {
  it("says overdue in days, due soon and assigned in words, and sorts overdue first", () => {
    const rows = assignmentRows(
      [
        assignment({ id: 1, status: "assigned", due_at: "2027-01-01T00:00:00+00:00" }),
        assignment({ id: 2, status: "overdue", due_at: "2026-10-18T09:00:00+00:00", course: { id: 6, title: "Fire" } }),
        assignment({ id: 3, status: "assigned", due_at: "2026-10-30T00:00:00+00:00", course: { id: 7, title: "Data" } }),
        assignment({ id: 4, status: "completed", completed_at: "2026-09-02T00:00:00+00:00", certificate: { id: 9, certificate_id: "abcd-12345678", status: "valid", expires_at: null } }),
        assignment({ id: 5, status: "cancelled" }),
        assignment({ id: 6, status: "overdue", due_at: "2026-09-21T00:00:00+00:00", course: { id: 8, title: "Older" } }),
      ],
      "en",
      NOW,
    );
    expect(rows.map((r) => [r.id, r.statusText])).toEqual([
      [6, "Overdue by 30 days"],
      [2, "Overdue by 3 days"],
      [3, "Due soon"],
      [1, "Assigned"],
      [4, "Completed"],
      [5, "Cancelled"],
    ]);
    expect(rows.find((r) => r.id === 2)!.tone).toBe("bad");
  });

  it("links open training to the course (Start, Continue or Review by progress) and completed to the certificate", () => {
    const progress = new Map([[6, 40], [7, 100]]);
    const rows = assignmentRows(
      [
        assignment({ id: 1, status: "assigned" }),
        assignment({ id: 2, status: "overdue", due_at: "2026-10-10T00:00:00+00:00", course: { id: 6, title: "Fire" } }),
        assignment({ id: 3, status: "assigned", course: { id: 7, title: "Data" } }),
        assignment({ id: 4, status: "completed", certificate: { id: 9, certificate_id: "abcd-12345678", status: "valid", expires_at: null } }),
      ],
      "en",
      NOW,
      progress,
    );
    const by = Object.fromEntries(rows.map((r) => [r.id, r]));
    expect(by[1]!.action).toEqual({ label: "Start", href: "/learn/5" });
    expect(by[2]!.action).toEqual({ label: "Continue", href: "/learn/6" });
    expect(by[3]!.action!.label).toBe("Review");
    expect(by[4]!.action).toBeNull();
    expect(by[4]!.certificate).toEqual({ href: "/certificates/verify/abcd-12345678", label: "View certificate" });
  });

  it("speaks Polish with the three plural forms", () => {
    const row = (n: number) => assignmentRows([assignment({ status: "overdue", due_at: new Date(NOW.getTime() - n * 86_400_000 - 3_600_000).toISOString() })], "pl", NOW)[0]!.statusText;
    expect([row(1), row(3), row(5), row(22)]).toEqual(["Po terminie o 1 dzień", "Po terminie o 3 dni", "Po terminie o 5 dni", "Po terminie o 22 dni"]);
  });

  it("handles a missing course and a missing due date", () => {
    const [row] = assignmentRows([assignment({ course: null, due_at: null })], "en", NOW);
    expect(row!.title).toBe("");
    expect(row!.action).toBeNull();
    expect(row!.dueText).toBe("No due date");
  });
});

describe("certificates", () => {
  const site = { webOrigin: "https://acme.example/", apiUrl: "https://acme.test/" };

  it("words the status: valid, expiring in n days, expired, revoked with the date", () => {
    const row = (over: Partial<Certificate>) => certificateRow(cert(over), "en", NOW, site);
    expect(row({}).statusText).toBe("Valid");
    expect(row({}).validUntil).toBe("Does not expire");
    expect(row({ expires_at: "2027-06-01T00:00:00+00:00" }).state).toBe("valid");
    expect(row({ expires_at: "2026-11-05T00:00:00+00:00" })).toMatchObject({ state: "expiring", statusText: "Expiring in 15 days", validUntil: "5 November 2026" });
    expect(row({ status: "expired", expires_at: "2026-09-01T00:00:00+00:00" }).statusText).toBe("Expired");
    expect(row({ status: "revoked", revoked_at: "2026-09-15T00:00:00+00:00" })).toMatchObject({ state: "revoked", statusText: "Revoked on 15 September 2026", badgeCode: null });
  });

  it("links the PDF route, the verification page and builds an escaped badge snippet", () => {
    const row = certificateRow(cert({}), "en", NOW, site);
    expect(row.downloadHref).toBe("/account/certificates/9.pdf");
    expect(row.verifyHref).toBe("/certificates/verify/b3f1c2d4-1111-4222-8333-444455556666");
    expect(row.version).toBe("3");
    expect(row.badgeCode).toBe(
      [
        '<script type="module" src="https://acme.example/embed/ulams-certificate-badge.js"></script>',
        '<ulams-certificate-badge certificate-id="b3f1c2d4-1111-4222-8333-444455556666" api-url="https://acme.test" verify-href="https://acme.example/certificates/verify/{id}"></ulams-certificate-badge>',
      ].join("\n"),
    );
    expect(badgeSnippet("https://x.test", "https://a.test", 'a"><script>')).not.toContain('"><script>');
    expect(certificateRow(cert({ certificate_id: null }), "en", NOW, site)).toMatchObject({ verifyHref: null, badgeCode: null });
  });

  it("matches a course certificate by class name or alias only for that course", () => {
    expect(isCourseCertificate({ assignable_type: "Ulams\\Courses\\Models\\Course", assignable_id: 5 }, 5)).toBe(true);
    expect(isCourseCertificate({ assignable_type: "course", assignable_id: "5" }, 5)).toBe(true);
    expect(isCourseCertificate({ assignable_type: "course", assignable_id: 6 }, 5)).toBe(false);
    expect(isCourseCertificate({ assignable_type: "topic.course_like", assignable_id: 5 }, 5)).toBe(false);
    expect(isCourseCertificate({ assignable_type: null, assignable_id: 5 }, 5)).toBe(false);
  });
});

describe("paths, skills and the account summary", () => {
  it("lists steps as done, available or locked with what unlocks them", () => {
    const path: PathEnrollment = {
      path_id: 2,
      title: "Onboarding",
      source: "rule",
      enrolled_at: null,
      due_at: null,
      completed_at: null,
      steps: [
        { course_id: 1, title: "Welcome", status: "done", waiting_for: [] },
        { course_id: 2, title: "Tools", status: "available", waiting_for: [] },
        { course_id: 3, title: "Security", status: "locked", waiting_for: [2, 1] },
      ],
    };
    const [row] = pathRows([path], "en");
    expect(row!.progress).toBe("1 of 3 steps done");
    expect(row!.steps.map((s) => [s.statusText, s.href, s.unlocks])).toEqual([
      ["Done", "/learn/1", null],
      ["Available", "/learn/2", null],
      ["Locked", null, "Unlocks after: Tools, Welcome"],
    ]);
  });

  it("groups skills by area, other last, with when they lapse", () => {
    const skill = (over: Partial<Skill>): Skill => ({ competency_id: 1, name: "Safety", code: "S1", area: "Compliance", courses: [{ course_id: 5, title: "Safety" }], earned_at: "2026-03-01T00:00:00+00:00", expires_at: null, ...over });
    const groups = skillGroups(
      [skill({ competency_id: 1, name: "Zebra", area: null }), skill({ competency_id: 2, name: "Fire", expires_at: "2027-03-01T00:00:00+00:00" }), skill({ competency_id: 3, name: "Audit", area: "Compliance" }), skill({ competency_id: 4, name: "Data", area: "Security" })],
      "en",
    );
    expect(groups.map((g) => g.area)).toEqual(["Compliance", "Security", null]);
    expect(groups[0]!.skills.map((s) => s.name)).toEqual(["Audit", "Fire"]);
    expect(groups[0]!.skills[1]).toMatchObject({ lapses: "Lapses 1 March 2027", lapsing: true, earned: "Earned 1 March 2026" });
    expect(groups[0]!.skills[0]!.lapses).toBe("Does not lapse");
  });

  it("counts what is due this month, overdue, certificates and skills; null where a call failed", () => {
    const data = {
      assignments: [
        assignment({ id: 1, due_at: "2026-10-28T00:00:00+00:00" }),
        assignment({ id: 2, due_at: "2026-11-02T00:00:00+00:00" }),
        assignment({ id: 3, status: "overdue", due_at: "2026-10-01T00:00:00+00:00" }),
        assignment({ id: 4, status: "completed" }),
      ],
      paths: [],
      certificates: [cert({}), cert({ id: 10, status: "revoked" })],
      skills: null,
    };
    expect(accountSummary(data, NOW)).toEqual({ dueThisMonth: 1, overdue: 1, certificates: 1, skills: null });
    expect(accountSummary({ assignments: null, paths: null, certificates: null, skills: null }, NOW)).toEqual({ dueThisMonth: 0, overdue: 0, certificates: null, skills: null });
  });

  it("only accepts known team filters", () => {
    expect(teamFilter("overdue")).toBe("overdue");
    expect(teamFilter("all")).toBeUndefined();
    expect(teamFilter("drop table")).toBeUndefined();
    expect(teamFilter(null)).toBeUndefined();
  });
});

describe("site origin", () => {
  it("prefers the proxy's forwarded host and falls back when the host is unusable", () => {
    expect(siteOrigin(new Headers({ "x-forwarded-host": "learn.acme.example, internal", host: "127.0.0.1:4321" }), true, "http://x")).toBe("https://learn.acme.example");
    expect(siteOrigin(new Headers({ host: "acme.app.localhost:4392" }), false, "http://x")).toBe("http://acme.app.localhost:4392");
    expect(siteOrigin(new Headers({ host: "bad host/../" }), false, "http://fallback.test")).toBe("http://fallback.test");
  });
});

describe("loading", () => {
  const ok = (data: unknown, meta?: unknown) => new Response(JSON.stringify({ success: true, data, meta }), { status: 200 });
  const stub = (routes: Record<string, () => Response>) =>
    vi.stubGlobal(
      "fetch",
      vi.fn(async (input: RequestInfo | URL) => {
        const path = new URL(String(input)).pathname;
        const route = routes[path];
        if (!route) throw new TypeError("offline");
        return route();
      }),
    );

  it("loads everything with the learner token and keeps going when one call fails", async () => {
    stub({
      "/api/compliance/my-assignments": () => ok([assignment({})]),
      "/api/pdfs": () => ok([cert({})], { total: 1 }),
      "/api/compliance/my-skills": () => new Response("{}", { status: 500 }),
    });
    const data = await loadTraining(tenant, "tok");
    expect(data.assignments).toHaveLength(1);
    expect(data.certificates).toHaveLength(1);
    expect(data.paths).toBeNull();
    expect(data.skills).toBeNull();
    const headers = (vi.mocked(fetch).mock.calls[0]![1] as RequestInit).headers as Record<string, string>;
    expect(headers.Authorization).toBe("Bearer tok");
    vi.unstubAllGlobals();
  });

  it("picks the valid certificate of the course, and null when none or the API is down", async () => {
    stub({ "/api/pdfs": () => ok([cert({ id: 1, status: "revoked" }), cert({ id: 2 }), cert({ id: 3, assignable_id: 6 })]) });
    expect((await loadCourseCertificate(tenant, "tok", 5))?.id).toBe(2);
    expect(await loadCourseCertificate(tenant, "tok", 99)).toBeNull();
    vi.unstubAllGlobals();
    stub({});
    expect(await loadCourseCertificate(tenant, "tok", 5)).toBeNull();
    vi.unstubAllGlobals();
  });

  it("returns an empty team for a non-manager and null on failure", async () => {
    stub({ "/api/compliance/team/assignments": () => ok([], { total: 0 }) });
    expect(await loadTeam(tenant, "tok")).toEqual([]);
    expect(String(vi.mocked(fetch).mock.calls[0]![0])).toContain("per_page=200");
    vi.unstubAllGlobals();
    stub({});
    expect(await loadTeam(tenant, "tok", "overdue")).toBeNull();
    vi.unstubAllGlobals();
  });
});
