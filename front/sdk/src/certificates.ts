/**
 * Certificates: the learner's own list (`GET /api/pdfs`), the public verification record
 * (`GET /api/certificates/verify/{id}`, no token needed) and the PDF download URL.
 */
import type { ClientOptions } from "./client.ts";
import { createRest, type Page } from "./rest.ts";

export type CertificateStatus = "valid" | "expired" | "revoked";

export interface Certificate {
  id: number;
  title: string | null;
  /** The public id used by the verification page and the badge. */
  certificate_id: string | null;
  assignable_type: string | null;
  assignable_id: number | string | null;
  status: CertificateStatus;
  expires_at: string | null;
  revoked_at: string | null;
  course_version: number | string | null;
  created_at: string | null;
}

export interface CertificateRecord {
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

export type CertificateClient = ReturnType<typeof createCertificateClient>;

export function createCertificateClient(options: ClientOptions) {
  const rest = createRest(options);
  return {
    /** The caller's own certificates; with a course, only that course's. */
    list: (query: { assignable_type?: string; assignable_id?: number; per_page?: number; page?: number } = {}): Promise<Page<Certificate>> =>
      rest.page<Certificate>("/api/pdfs", { per_page: 100, ...query }),
    /** Public. Null when the id is unknown (404). */
    verify: async (certificateId: string): Promise<CertificateRecord | null> => {
      try {
        return await rest.data<CertificateRecord>(`/api/certificates/verify/${encodeURIComponent(certificateId)}`);
      } catch (error) {
        if ((error as { status?: number }).status === 404) return null;
        throw error;
      }
    },
    /** The PDF of one certificate; needs the owner's token. */
    downloadUrl: (id: number): string => `${rest.origin}/api/pdfs/generate/${id}`,
  };
}
