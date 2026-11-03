/**
 * Learner side of compliance (`/api/compliance`): my required training, my learning paths, my skills
 * and, for a manager, the training of their direct reports. REST calls return the unwrapped `data`.
 * In the browser `baseUrl` is the learner BFF (`/bff`); on a server pass the tenant API and the token.
 */
import type { ClientOptions } from "./client.ts";
import { createRest, type Page } from "./rest.ts";

/** `overdue` is derived by the API from an open assignment past its due date. */
export type AssignmentStatus = "assigned" | "overdue" | "completed" | "cancelled";

export interface AssignmentCertificate {
  id: number;
  certificate_id: string | null;
  status: "valid" | "expired" | "revoked";
  expires_at: string | null;
}

export interface Assignment {
  id: number;
  /** Only in the manager view. */
  user: { id: number; name: string | null; email: string | null } | null;
  course: { id: number; title: string | null } | null;
  source: string;
  status: AssignmentStatus;
  assigned_at: string | null;
  due_at: string | null;
  completed_at: string | null;
  escalation_level: number;
  certificate: AssignmentCertificate | null;
}

export type PathStepStatus = "done" | "available" | "locked";

export interface PathStep {
  course_id: number;
  title: string | null;
  status: PathStepStatus;
  /** Course ids that must be done first. */
  waiting_for: number[];
}

export interface PathEnrollment {
  path_id: number;
  title: string | null;
  source: string;
  enrolled_at: string | null;
  due_at: string | null;
  completed_at: string | null;
  steps: PathStep[];
}

export interface Skill {
  competency_id: number;
  name: string;
  code: string | null;
  /** The parent competency, when there is one. */
  area: string | null;
  courses: Array<{ course_id: number; title: string | null }>;
  earned_at: string | null;
  /** When the skill lapses; null when it does not. */
  expires_at: string | null;
}

export type ComplianceClient = ReturnType<typeof createComplianceClient>;

export function createComplianceClient(options: ClientOptions) {
  const rest = createRest(options);
  const P = "/api/compliance";
  return {
    myAssignments: async (): Promise<Assignment[]> => (await rest.data<Assignment[]>(`${P}/my-assignments`)) ?? [],
    myPaths: async (): Promise<PathEnrollment[]> => (await rest.data<PathEnrollment[]>(`${P}/my-paths`)) ?? [],
    mySkills: async (): Promise<Skill[]> => (await rest.data<Skill[]>(`${P}/my-skills`)) ?? [],
    /** Empty for everyone who is nobody's manager. */
    teamAssignments: (query: { status?: AssignmentStatus; per_page?: number; page?: number } = {}): Promise<Page<Assignment>> => rest.page<Assignment>(`${P}/team/assignments`, query),
  };
}
