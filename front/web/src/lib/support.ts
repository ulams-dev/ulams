/**
 * "Extra help for you" on the lesson page (Learner Insights, M-UI1). The page asks the API for the learner's
 * own support items of the topic and turns each into one `SupportCard` node of the UI catalogue, whose
 * children are the stored blocks (component and props as stored), each followed by its sources. Nothing here
 * renders markup: the catalogue validates every node and falls back to text for a block it cannot render.
 */
import { createInsightsClient, type SupportItem, type Tenant } from "@ulams/sdk";
import { REMEDIATION_COMPONENTS } from "@ulams/ui/registry";
import type { UiLocale } from "@ulams/ui/i18n";
import type { UiNode } from "@ulams/ui/render-core";
import { tenantFetch } from "./api-transport.ts";

export const SUPPORT_TIMEOUT_MS = 1_500;

/** The learner's support items of a topic. Never fatal: a slow answer, an error or an older API means no card. */
export async function getSupport(tenant: Tenant, token: string, courseId: number, topicId: number, locale: UiLocale): Promise<SupportItem[]> {
  const client = createInsightsClient({ baseUrl: tenant.apiUrl, fetch: tenantFetch(tenant), token, timeoutMs: SUPPORT_TIMEOUT_MS, headers: { "X-Locale": locale } });
  try {
    const support = await client.support(courseId, topicId);
    return support.enabled ? support.items : [];
  } catch {
    return [];
  }
}

/** Blocks that take the interface language (their fixed strings); the others have no strings of their own. */
const LOCALE_AWARE = new Set(["PracticeQuestion", "ElaborativeQuestion"]);
const ALLOWED: ReadonlySet<string> = new Set(REMEDIATION_COMPONENTS);

export const supportCardId = (item: Pick<SupportItem, "assignmentId">): string => `support-${item.assignmentId}`;

/** One SupportCard node per item; a ready item carries its blocks and sources, a generating one only its status. */
export function supportDocs(items: SupportItem[], locale: UiLocale): UiNode[] {
  const nodes: UiNode[] = [];
  for (const item of items) {
    const id = supportCardId(item);
    if (item.status === "generating") {
      nodes.push({ component: "SupportCard", id, props: { assignmentId: item.assignmentId, status: "generating", locale } });
      continue;
    }
    if (item.status !== "ready" || !item.document) continue;
    const labels = new Map(item.citations.map((c) => [c.fragmentId, c.label]));
    const children: UiNode[] = [];
    item.document.blocks.forEach((block, i) => {
      if (!ALLOWED.has(block.component)) return;
      const props = LOCALE_AWARE.has(block.component) ? { ...block.props, locale } : block.props;
      children.push({ component: block.component, id: `${id}-b${i}`, props });
      const sources = [...new Set(block.citations ?? [])].flatMap((fragmentId) => (labels.has(fragmentId) ? [{ label: labels.get(fragmentId)! }] : []));
      if (sources.length > 0) children.push({ component: "CitationList", id: `${id}-s${i}`, props: { items: sources.slice(0, 12), locale } });
    });
    if (children.length === 0) continue;
    // "Explain it in your own words": one box at the end of each ready card (the API answers 503 when AI is off)
    children.push({ component: "FeynmanReflection", id: `${id}-reflect`, props: { assignmentId: item.assignmentId, locale } });
    nodes.push({
      component: "SupportCard",
      id,
      props: {
        assignmentId: item.assignmentId,
        status: "ready",
        summary: item.document.summary,
        reasons: item.reasons.slice(0, 6),
        ...(item.aiLabel ? { aiLabel: item.aiLabel } : {}),
        ...(item.feedback ? { feedback: item.feedback } : {}),
        locale,
      },
      children,
    });
  }
  // Several cards on one lesson are several landmarks: each needs its own name ("Extra help for you (1 of 2)").
  if (nodes.length > 1) {
    const total = Math.min(nodes.length, 20);
    nodes.forEach((node, i) => {
      node.props = { ...node.props, position: Math.min(i + 1, 20), total };
    });
  }
  return nodes;
}

/** A card long enough that the lesson header offers a link to it. */
export const supportIsLong = (nodes: UiNode[]): boolean => nodes.some((n) => (n.children?.filter((c) => c.component !== "FeynmanReflection").length ?? 0) > 3);
