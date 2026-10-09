// The course text format of api/database/seeds/Demo/Support/ModuleFile.php, read in Node (tests and manual checks).
import assert from "node:assert/strict";

/** The same format as ModuleFile.php. */
export function parseModule(text) {
  text = text.replace(/\r\n/g, "\n");
  const meta = {};
  const front = text.match(/^---\n([\s\S]*?)\n---\n/);
  if (front) {
    for (const line of front[1].split("\n")) {
      const kv = line.match(/^(\w+):\s*(.*)$/);
      if (kv) meta[kv[1]] = kv[2].trim();
    }
    text = text.slice(front[0].length);
  }
  const blocks = [];
  let current = null;
  for (const line of text.split("\n")) {
    if (current === null) {
      const open = line.match(/^:::\s+(\w+)(.*)$/);
      if (open) {
        const attrs = {};
        for (const m of open[2].matchAll(/(\w+)=(?:"([^"]*)"|(\S+))/g)) attrs[m[1]] = m[3] ?? m[2];
        current = { kind: open[1], attrs, body: [] };
      } else assert.equal(line.trim(), "", `text outside a block: ${line}`);
    } else if (line.trimEnd() === ":::") {
      current.body = current.body.join("\n").trim();
      blocks.push(current);
      current = null;
    } else current.body.push(line);
  }
  assert.equal(current, null, "unclosed block");
  return { meta, blocks };
}

export function giftQuestions(body) {
  return body
    .split("\n")
    .filter((l) => !l.trimStart().startsWith("//"))
    .join("\n")
    .split(/\n\s*\n/)
    .map((q) => q.trim())
    .filter(Boolean);
}

/** The same decision as GiftQuestionService::getType. */
export function giftType(q) {
  if (!(q.includes("{") && q.includes("}"))) return "description";
  const answer = q.slice(q.indexOf("{") + 1, q.lastIndexOf("}")).trim();
  if (!answer) return "essay";
  if (answer.startsWith("#")) return "numerical";
  if (answer.includes("~%")) return "multiple_right";
  if (answer.includes("~") && answer.includes("=")) return "multiple_choice";
  if (answer.includes("->")) return "matching";
  if (/^(T|F|TRUE|FALSE)$/.test(answer)) return "true_false";
  return "short";
}

