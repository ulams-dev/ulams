import { describe, expect, it, vi } from "vitest";
import { isCertificateId, statusCopy, verifyCertificate, type CertificateView } from "../../src/lib/certificate-verify.ts";

const tenant = { apiUrl: "https://acme.example" };
const base: CertificateView = {
  certificate_id: "a3f1c2d4-1111-4222-8333-444455556666",
  status: "valid",
  title: "Certificate",
  course_title: "Safety",
  holder_name: "Ada Lovelace",
  issued_at: "2026-03-01T00:00:00+00:00",
  expires_at: "2027-03-01T00:00:00+00:00",
  revoked_at: null,
  issuer: "Acme",
};
const reply = (status: number, body: unknown = {}) => vi.fn(async () => new Response(JSON.stringify(body), { status })) as unknown as typeof fetch;

describe("certificate ids", () => {
  it("accepts random UUIDs and refuses anything that cannot be one", () => {
    expect(isCertificateId(base.certificate_id)).toBe(true);
    expect(isCertificateId("short")).toBe(false);
    expect(isCertificateId("../../etc/passwd")).toBe(false);
    expect(isCertificateId("a".repeat(65))).toBe(false);
  });
});

describe("verifyCertificate", () => {
  it("returns the record for a known certificate", async () => {
    const fetcher = reply(200, { data: base });
    const result = await verifyCertificate(tenant, base.certificate_id, fetcher);
    expect(result).toEqual({ kind: "found", certificate: base });
    expect(fetcher).toHaveBeenCalledWith(`https://acme.example/api/certificates/verify/${base.certificate_id}`, expect.anything());
  });
  it("tells an unknown id from an unavailable API", async () => {
    expect(await verifyCertificate(tenant, base.certificate_id, reply(404))).toEqual({ kind: "unknown" });
    expect(await verifyCertificate(tenant, base.certificate_id, reply(500))).toEqual({ kind: "unavailable" });
    expect(await verifyCertificate(tenant, base.certificate_id, reply(200, { data: { status: "weird" } }))).toEqual({ kind: "unavailable" });
    expect(await verifyCertificate(tenant, base.certificate_id, vi.fn(async () => { throw new Error("down"); }) as unknown as typeof fetch)).toEqual({ kind: "unavailable" });
  });
  it("does not call the API for a malformed id", async () => {
    const fetcher = reply(200, { data: base });
    expect(await verifyCertificate(tenant, "../x", fetcher)).toEqual({ kind: "unknown" });
    expect(fetcher).not.toHaveBeenCalled();
  });
});

describe("statusCopy", () => {
  it("writes the status out for each state", () => {
    expect(statusCopy(base)).toEqual({ heading: "This certificate is valid", text: "It is valid until 1 March 2027." });
    expect(statusCopy({ ...base, expires_at: null }).text).toBe("It does not expire.");
    expect(statusCopy({ ...base, status: "expired", expires_at: "2026-04-01T00:00:00+00:00" }).heading).toBe("This certificate has expired");
    expect(statusCopy({ ...base, status: "revoked", revoked_at: "2026-05-02T10:00:00+00:00" }).text).toContain("2 May 2026");
  });
});
