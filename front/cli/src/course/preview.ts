import type { Obj } from "./types.ts";
import { isObj } from "./types.ts";

/* A preview academy has no sources: a fragment id that a folder cites does not exist there, and the server refuses a document
 * that cites one. `forPreview` turns the folder's document into what a person wrote for that academy: no citations, and the
 * elements that may say who wrote them say `author` (Blueprint v3: block, lesson, question, interaction, FAQ item). The folder
 * itself is never changed, and a preview is never published or used as the course's real version. */

/** Elements that may carry `origin` (everything else with `citations`, such as an objective, only loses them). */
const takesOrigin = (node: Obj, key: string | null): boolean =>
  typeof node.markdown === "string" || typeof node.stem === "string" || Array.isArray(node.blocks) || key === "interaction" || key === "faq";

export function forPreview(doc: Obj): Obj {
  const walk = (node: unknown, key: string | null, parentKey: string | null): unknown => {
    if (Array.isArray(node)) return node.map((n) => walk(n, key, key));
    if (!isObj(node)) return node;
    const out: Obj = {};
    for (const [k, v] of Object.entries(node)) out[k] = walk(v, k, key);
    if (Array.isArray(out.citations)) {
      out.citations = [];
      // an array element is named by the key of the array (`faq` items, `selfChecks` questions); a property by its own key
      if (takesOrigin(out, parentKey === "faq" ? "faq" : key)) out.origin = "author";
    }
    return out;
  };
  const copy = walk(JSON.parse(JSON.stringify(doc)), null, null) as Obj;
  return { ...copy, sources: [] };
}
