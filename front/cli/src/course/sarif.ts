import { CATALOG } from "./issues.ts";
import type { Issue, Severity } from "./types.ts";

const LEVEL: Record<Severity, "error" | "warning" | "note"> = { error: "error", warning: "warning", info: "note" };

/** SARIF 2.1.0, so the GitHub Action can show issues inline on a pull request. Paths are relative to the course folder. */
export function toSarif(issues: Issue[], version: string, prefix = ""): Record<string, unknown> {
  const codes = Object.keys(CATALOG) as Array<keyof typeof CATALOG>;
  return {
    $schema: "https://json.schemastore.org/sarif-2.1.0.json",
    version: "2.1.0",
    runs: [
      {
        tool: {
          driver: {
            name: "ulams course validate",
            version,
            informationUri: "https://ulams.dev/developers/course-folder-format/",
            rules: codes.map((code) => ({
              id: code,
              name: code,
              shortDescription: { text: CATALOG[code].title },
              help: { text: CATALOG[code].hint },
              defaultConfiguration: { level: LEVEL[CATALOG[code].severity] },
            })),
          },
        },
        results: issues.map((i) => ({
          ruleId: i.code,
          ruleIndex: codes.indexOf(i.code as keyof typeof CATALOG),
          level: LEVEL[i.severity],
          message: { text: i.hint ? `${i.message} ${i.hint}` : i.message },
          locations: [
            {
              physicalLocation: {
                artifactLocation: { uri: `${prefix}${i.file}`, uriBaseId: "%SRCROOT%" },
                region: { startLine: i.line, startColumn: i.column },
              },
            },
          ],
          ...(i.element ? { partialFingerprints: { element: i.element } } : {}),
        })),
      },
    ],
  };
}

/** Human output: `file:line:column severity CODE message`, the hint underneath, a summary last. */
export function toText(issues: Issue[], files: number): string {
  const lines: string[] = [];
  for (const i of issues) {
    lines.push(`${i.file}:${i.line}:${i.column}  ${i.severity.padEnd(7)} ${i.code}  ${i.message}`);
    lines.push(`    hint: ${i.hint}`);
  }
  const count = (s: Severity) => issues.filter((i) => i.severity === s).length;
  const part = (n: number, word: string) => `${n} ${word}${n === 1 ? "" : "s"}`;
  lines.push(`${issues.length ? "\n" : ""}${part(count("error"), "error")}, ${part(count("warning"), "warning")}, ${part(count("info"), "note")} in ${part(files, "file")}`);
  return lines.join("\n");
}
