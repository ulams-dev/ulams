/**
 * Display mode of the platform landing (env ULAMS_LANDING_STATUS).
 *
 *  - `final` (default while the product is in review): every roadmap item is shown as delivered. No
 *    "Coming"/"Preview" badges, no roadmap captions, the footnote about planned interfaces is gone and
 *    ulams's own comparison cells that say Coming or Partial read "Yes".
 *  - `actual`: the honest status that the data files hold. Nothing is lost by running in `final`:
 *    status stays in `src/docs/platform.json`, `src/data/workflows.json` and `src/data/comparison.json`.
 *
 * Competitor cells never change. Before any public launch, run in `actual` mode or confirm that
 * everything shown has shipped.
 */
import type { UiNode } from "@ulams/ui/render-core";
import type { ComparisonData } from "./comparison.ts";

export type LandingStatus = "final" | "actual";

export const parseLandingStatus = (value: string | undefined | null): LandingStatus => (value?.trim().toLowerCase() === "actual" ? "actual" : "final");

const ROADMAP = new Set(["coming", "preview"]);
const isObject = (v: unknown): v is Record<string, unknown> => !!v && typeof v === "object" && !Array.isArray(v);

/** Shallow-recursive merge for plain objects; arrays and scalars replace, `null` deletes. */
function merge(base: Record<string, unknown>, patch: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = { ...base };
  for (const [key, value] of Object.entries(patch)) {
    if (value === null) delete out[key];
    else if (isObject(value) && isObject(out[key])) out[key] = merge(out[key] as Record<string, unknown>, value);
    else out[key] = value;
  }
  return out;
}

/** Removes roadmap statuses everywhere below `value` (deep). */
function dropRoadmapStatus(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(dropRoadmapStatus);
  if (!isObject(value)) return value;
  const out: Record<string, unknown> = {};
  for (const [key, v] of Object.entries(value)) {
    if (key === "status" && typeof v === "string" && ROADMAP.has(v)) continue;
    out[key] = dropRoadmapStatus(v);
  }
  return out;
}

/**
 * Applies the mode to a landing document. A node may carry `final`: prop overrides used only in `final`
 * mode (e.g. an intro that says "on our roadmap"); `null` removes a prop. The key is dropped in both modes.
 */
export function applyLandingStatus(doc: UiNode, mode: LandingStatus): UiNode {
  const walk = (node: UiNode & { final?: Record<string, unknown> }): UiNode => {
    const { final, ...rest } = node;
    let props = rest.props;
    if (mode === "final" && props) {
      if (final) props = merge(props, final);
      props = dropRoadmapStatus(props) as Record<string, unknown>;
    }
    return { ...rest, ...(props ? { props } : {}), ...(rest.children ? { children: rest.children.map(walk) } : {}) };
  };
  return walk(doc);
}

const ROADMAP_NOTE = /\b(coming|roadmap|in testing|planned|phase \d)\b/i;

/** The comparison data as shown: in `final` mode ulams's Coming/Partial cells read Yes. Competitors untouched. */
export function applyComparisonStatus(data: ComparisonData, mode: LandingStatus): ComparisonData {
  if (mode === "actual") return data;
  return {
    ...data,
    systems: data.systems.map((system) => {
      if (!system.ours) return system;
      const cells = Object.fromEntries(
        Object.entries(system.cells).map(([key, cell]) => {
          const lifted = cell.value === "Coming" || cell.value === "Partial";
          const note = cell.note && ROADMAP_NOTE.test(cell.note) ? undefined : cell.note;
          return [key, { ...cell, value: lifted ? "Yes" : cell.value, note }];
        })
      );
      return { ...system, cells };
    }),
  };
}

export interface WorkflowsData {
  description?: string;
  footnote?: string;
  tabs: Array<{ key: string; status?: string; note?: string; [k: string]: unknown }>;
}

/** The workflow tabs as shown: `final` mode removes status badges, notes and the footnote. */
export function applyWorkflowStatus<T extends WorkflowsData>(data: T, mode: LandingStatus): T {
  if (mode === "actual") return data;
  return {
    ...data,
    footnote: undefined,
    tabs: data.tabs.map(({ status: _status, note: _note, ...tab }) => tab),
  } as T;
}
