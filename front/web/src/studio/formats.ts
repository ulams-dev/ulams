/**
 * The format line and the interactive parts of a lesson for the preview card: LiaScript self-checks,
 * the H5P activity or the interactive from the library (ADR 0050). Pure, so it is unit tested.
 */
import type { BlueprintLesson, BlueprintInteraction } from "@ulams/sdk";

const LIBRARY: Record<string, string> = { "H5P.Blanks": "Fill in the blanks", "H5P.DragText": "Drag the words", "H5P.Dialogcards": "Dialog cards" };

type Cite = (ids: string[]) => Array<{ fragmentId: string; label: string }>;

function count(data: Record<string, unknown>, key: string): number {
  const list = data[key];
  return Array.isArray(list) ? list.length : 0;
}

/** "3 blanks", "5 words", "6 cards" for an H5P interaction. */
export function h5pSize(interaction: Extract<BlueprintInteraction, { kind: "h5p" }>): string {
  const data = interaction.data;
  if (interaction.library === "H5P.Blanks") {
    const items = Array.isArray(data.items) ? (data.items as Array<{ blanks?: unknown[] }>) : [];
    const n = items.reduce((sum, item) => sum + (item.blanks?.length ?? 0), 0);
    return `${n} blank${n === 1 ? "" : "s"}`;
  }
  if (interaction.library === "H5P.DragText") return `${count(data, "words")} words`;
  return `${count(data, "cards")} cards`;
}

export function formatLine(lesson: BlueprintLesson): string | undefined {
  switch (lesson.contentType) {
    case "liascript": {
      const n = lesson.selfChecks?.length ?? 0;
      return `LiaScript${n > 0 ? ` · ${n} self-check${n === 1 ? "" : "s"}` : ""}`;
    }
    case "h5p":
      return lesson.interaction?.kind === "h5p" ? `H5P · ${LIBRARY[lesson.interaction.library] ?? lesson.interaction.library} · ${h5pSize(lesson.interaction)}` : "H5P";
    case "interactive":
      return lesson.interaction?.kind === "interactive" ? `Interactive · ${lesson.interaction.title}` : "Interactive";
    default:
      return undefined;
  }
}

function describe(interaction: BlueprintInteraction): string {
  if (interaction.kind === "interactive") return interaction.caption;
  const data = interaction.data as { instruction?: string; title?: string };
  return String(data.instruction ?? data.title ?? interaction.title);
}

/** Props for LessonPreviewCard: `format` and `extras` (only for lessons that have something to show). */
export function lessonExtras(lesson: BlueprintLesson, cite: Cite): { format?: string; extras?: Array<Record<string, unknown>> } {
  const format = formatLine(lesson);
  const extras: Array<Record<string, unknown>> = [];
  (lesson.selfChecks ?? []).forEach((q, i) =>
    extras.push({ id: q.id, kind: "check", label: `Self-check ${i + 1}`, text: q.stem, editable: true, citations: cite(q.citations) })
  );
  if (lesson.interaction) {
    extras.push({ id: lesson.interaction.id, kind: "activity", label: lesson.interaction.title, text: describe(lesson.interaction).slice(0, 1000), editable: false, citations: cite(lesson.interaction.citations) });
  }
  return { ...(format ? { format } : {}), ...(extras.length ? { extras } : {}) };
}
