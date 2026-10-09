export interface Change {
  op: "add" | "replace" | "remove";
  path: string;
  from?: unknown;
  to?: unknown;
}

const same = (a: unknown, b: unknown) => JSON.stringify(a ?? null) === JSON.stringify(b ?? null) || (a !== null && b !== null && a !== undefined && b !== undefined && String(a) === String(b) && typeof a !== "object");

/** Structural diff of the fields a request would send against the current resource (top-level keys). */
export function diffFields(current: Record<string, unknown> | null, desired: Record<string, unknown>): Change[] {
  const changes: Change[] = [];
  for (const [key, to] of Object.entries(desired)) {
    if (to === undefined) continue;
    if (!current) {
      changes.push({ op: "add", path: `/${key}`, to });
      continue;
    }
    if (!(key in current)) changes.push({ op: "add", path: `/${key}`, to });
    else if (!same(current[key], to)) changes.push({ op: "replace", path: `/${key}`, from: current[key], to });
  }
  return changes;
}
