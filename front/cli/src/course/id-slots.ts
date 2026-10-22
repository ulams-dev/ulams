import { isObj, type Path } from "./types.ts";

export type FileKind = "ulams" | "course" | "module" | "lesson" | "quiz";

/** Paths of the maps in a file's data that must have an `id` but do not. `fmt` mints them, `validate` reports them. */
export function missingIds(kind: FileKind, data: unknown): Path[] {
  const out: Path[] = [];
  const need = (node: unknown, path: Path) => {
    if (isObj(node) && !("id" in node)) out.push(path);
  };
  const questions = (list: unknown, base: Path) => {
    if (!Array.isArray(list)) return;
    list.forEach((q, i) => {
      need(q, [...base, i]);
      if (isObj(q) && Array.isArray(q.options)) q.options.forEach((o, j) => need(o, [...base, i, "options", j]));
    });
  };
  if (!isObj(data)) return out;
  switch (kind) {
    case "course":
      need(data, []);
      if (Array.isArray(data.objectives)) data.objectives.forEach((o, i) => need(o, ["objectives", i]));
      break;
    case "module":
      need(data, []);
      break;
    case "lesson":
      need(data, []);
      if (Array.isArray(data.objectives)) data.objectives.forEach((o, i) => need(o, ["objectives", i]));
      questions(data.selfChecks, ["selfChecks"]);
      need(data.interaction, ["interaction"]);
      break;
    case "quiz":
      need(data, []);
      questions(data.questions, ["questions"]);
      break;
    case "ulams":
      break;
  }
  return out;
}
