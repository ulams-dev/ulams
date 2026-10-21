/**
 * The facts of the accessibility statement come from the tenant's public settings (group `global`), because the
 * operator, as the service provider under the European Accessibility Act, owns them. Everything is optional:
 * the page works with none of them and then says what is missing instead of inventing it.
 *
 *   global.companyName               the name used in the text (else the tenant slug)
 *   global.accessibilityEmail        contact for accessibility barriers (a valid address, else ignored)
 *   global.accessibilityEnforcement  who to complain to, replaces the default sentence
 *   global.accessibilityNotes        known issues of this academy, plain text, blank lines separate paragraphs
 *   global.accessibilityUpdated      date of the last review (ISO 8601)
 */
type Settings = { global?: Record<string, unknown> } | null | undefined;

export interface StatementModel {
  name: string;
  email: string | null;
  enforcement: string | null;
  notes: string[];
  updated: string | null;
}

const text = (value: unknown, max: number): string | null => {
  if (typeof value !== "string") return null;
  const clean = value.replace(/\r\n?/g, "\n").trim();
  return clean === "" ? null : clean.slice(0, max);
};

/** A single plain address: no spaces, one @, a dot in the domain. Anything else is not turned into a link. */
export const validEmail = (value: string | null): string | null => (value && /^[^\s@<>"',;]+@[^\s@<>"',;]+\.[^\s@<>"',;]+$/.test(value) ? value : null);

export function statementModel(settings: Settings, slug: string): StatementModel {
  const g = settings?.global ?? {};
  const notes = text(g.accessibilityNotes, 2000);
  return {
    name: text(g.companyName, 120) ?? slug,
    email: validEmail(text(g.accessibilityEmail, 200)),
    enforcement: text(g.accessibilityEnforcement, 600),
    notes: notes ? notes.split(/\n{2,}/).map((p) => p.trim()).filter(Boolean) : [],
    updated: text(g.accessibilityUpdated, 40),
  };
}
