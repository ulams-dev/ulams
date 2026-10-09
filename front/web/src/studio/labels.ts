import type { SessionStatus } from "@ulams/sdk";

/** Session status as shown to the author (top bar, session list). */
export const STATUS_LABEL: Record<SessionStatus, string> = {
  draft: "Draft · not published",
  ingesting: "Reading your source",
  interviewing: "Interview",
  outlining: "Drafting the outline",
  outline_review: "Outline proposed · waiting for your approval",
  generating: "Generating lessons",
  apply_review: "Ready to apply · waiting for your approval",
  applying: "Applying to your academy",
  applied: "Applied · unpublished",
  failed: "Needs attention",
};
