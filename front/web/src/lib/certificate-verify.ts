import type { Tenant } from "@ulams/sdk";
import { tenantFetch } from "./api-transport.ts";

export type CertificateStatus = "valid" | "expired" | "revoked";

export interface CertificateView {
  certificate_id: string;
  status: CertificateStatus;
  title: string | null;
  course_title: string | null;
  holder_name: string | null;
  issued_at: string | null;
  expires_at: string | null;
  revoked_at: string | null;
  issuer: string | null;
}

export type VerifyResult = { kind: "found"; certificate: CertificateView } | { kind: "unknown" } | { kind: "unavailable" };

/** Ids are random UUIDs; anything else cannot exist, so the API is not asked. */
export const isCertificateId = (id: string): boolean => /^[A-Za-z0-9-]{8,64}$/.test(id);

const STATUSES: CertificateStatus[] = ["valid", "expired", "revoked"];

/** Fetches the public verification record. Never throws: an unreachable API is "unavailable", not "unknown". */
export async function verifyCertificate(tenant: Pick<Tenant, "apiUrl">, id: string, fetcher: typeof fetch = tenantFetch(tenant)): Promise<VerifyResult> {
  if (!isCertificateId(id)) return { kind: "unknown" };
  try {
    const response = await fetcher(`${tenant.apiUrl}/api/certificates/verify/${encodeURIComponent(id)}`, {
      headers: { Accept: "application/json" },
      signal: AbortSignal.timeout(5_000),
    });
    if (response.status === 404) return { kind: "unknown" };
    if (!response.ok) return { kind: "unavailable" };
    const data = ((await response.json()) as { data?: CertificateView }).data;
    if (!data || !STATUSES.includes(data.status)) return { kind: "unavailable" };
    return { kind: "found", certificate: data };
  } catch {
    return { kind: "unavailable" };
  }
}

const DATE = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "long", year: "numeric", timeZone: "UTC" });

export const formatDate = (iso: string | null): string => {
  if (!iso) return "";
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? "" : DATE.format(date);
};

/** Status headline and explanation; the status is always written out, never colour alone. */
export function statusCopy(certificate: CertificateView): { heading: string; text: string } {
  switch (certificate.status) {
    case "valid":
      return {
        heading: "This certificate is valid",
        text: certificate.expires_at ? `It is valid until ${formatDate(certificate.expires_at)}.` : "It does not expire.",
      };
    case "expired":
      return { heading: "This certificate has expired", text: `It expired on ${formatDate(certificate.expires_at)}. The holder needs to renew it.` };
    case "revoked":
      return { heading: "This certificate has been revoked", text: `It was withdrawn on ${formatDate(certificate.revoked_at)} and is no longer valid.` };
  }
}
