import { z } from "zod";
import { applyPlanToDisk, readFolder } from "../course/disk.ts";
import { changedPaths, planFormat } from "../course/fmt.ts";
import { mintId } from "../course/ids.ts";
import { toSarif, toText } from "../course/sarif.ts";
import { summarize } from "../course/issues.ts";
import type { Issue } from "../course/types.ts";
import { validateFiles } from "../course/validate.ts";
import { CliError } from "../errors.ts";
import type { AnyCommand, Ctx, Result } from "../registry/types.ts";
import { defineCommand } from "./define.ts";

/* Course-as-code, the offline half (ADR 0160): `course validate` and `course fmt` work on a folder of Markdown and
 * YAML with no server and no network, so they run in an editor, a pre-commit hook and CI. */

const common = { kind: "local" as const, anonymous: true, idempotent: false, scopes: [] as string[], audience: ["author" as const], mcp: { expose: false }, since: "0.2.0" };
const dir = z.string().optional().describe("Course folder (the one holding ulams.yaml). Default: the current directory.");

type Format = "text" | "json" | "sarif";

function formatOf(ctx: Ctx, explicit: Format | undefined): Format {
  if (explicit) return explicit;
  return ctx.flags.json || ctx.flags.output === "json" || ctx.flags.output === "ndjson" || ctx.flags.output === "yaml" || ctx.env.ULAMS_OUTPUT === "json" ? "json" : "text";
}

const out = (ctx: Ctx, text: string) => (ctx.io.stdout ? ctx.io.stdout(text) : void process.stdout.write(`${text}\n`));

function fileCount(files: Map<string, string>): number {
  return [...files.keys()].filter((p) => /\.(md|yaml)$/.test(p)).length;
}

/** Prints findings the way `format` asks for and returns the result to hand back (or throws for the JSON error envelope). */
async function report(ctx: Ctx, issues: Issue[], files: number, initialFormat: Format, failed: boolean, version: string): Promise<Result> {
  let format = initialFormat;
  const summary = summarize(issues);
  if (format === "sarif") {
    const sarif = `${JSON.stringify(toSarif(issues, version), null, 2)}`;
    if (!ctx.flags.out) {
      out(ctx, sarif);
      return { data: null, handled: true, exitCode: failed ? 7 : 0 };
    }
    // With --out the file holds the SARIF and the terminal gets the usual report.
    await ctx.fs.writeFile(ctx.flags.out, `${sarif}\n`);
    format = formatOf(ctx, undefined);
  }
  if (format === "text") {
    out(ctx, toText(issues, files));
    return { data: null, handled: true, exitCode: failed ? 7 : 0 };
  }
  if (failed) {
    throw new CliError("VALIDATION_FAILED", `${summary.errors} error${summary.errors === 1 ? "" : "s"}, ${summary.warnings} warning${summary.warnings === 1 ? "" : "s"} in the course folder.`, {
      hint: "Fix the issues listed in error.details.issues (file, line, column), then run the command again.",
      details: { valid: false, summary, files, issues },
    });
  }
  return { data: { valid: true, summary, files, issues, ...(initialFormat === "sarif" && ctx.flags.out ? { sarif: ctx.flags.out } : {}) } };
}

export const courseFolderCommands: AnyCommand[] = [
  defineCommand({
    ...common,
    id: "course.validate",
    summary: "Check a course folder offline: files, directive grammar, Blueprint v3 and the deterministic checks",
    description:
      "Reads the course folder (ulams.yaml, course.yaml, modules/) and reports every problem by file, line and column. No server, no network. Errors exit 7 (VALIDATION_FAILED); with --strict warnings do too. --fix first applies what `course fmt` does (ids, loose Markdown, order prefixes, asset hashes). --format sarif prints SARIF 2.1.0 for code scanning; --out writes it to a file instead.",
    kind: "local",
    positionals: ["dir"],
    input: z.object({
      dir,
      fix: z.boolean().optional().describe("Apply `course fmt` first (mint missing ids, wrap loose Markdown, renumber, fill asset hashes)."),
      strict: z.boolean().optional().describe("Treat warnings as failures."),
      format: z.enum(["text", "json", "sarif"]).optional().describe("text (default in a terminal), json (the usual envelope) or sarif (SARIF 2.1.0 on stdout, or in --out)."),
    }),
    output: z.unknown(),
    examples: [
      { title: "Validate the current folder", argv: "course validate" },
      { title: "Validate a folder and fix what is mechanical", argv: "course validate ./kubernetes-101 --fix" },
      { title: "SARIF for GitHub code scanning", argv: "course validate --format sarif --out ulams.sarif" },
      { title: "Machine-readable issues", argv: "course validate --json" },
    ],
    async run(ctx, i) {
      let folder = readFolder(i.dir ?? ".");
      if (i.fix) {
        const plan = planFormat(folder.files, { mint: mintId, assets: folder.assets });
        if (plan.blocking.length === 0) {
          applyPlanToDisk(folder.root, plan);
          folder = readFolder(i.dir ?? ".");
        }
      }
      const { issues } = validateFiles(folder.files, folder.assets);
      const { errors, warnings } = summarize(issues);
      const failed = errors > 0 || (Boolean(i.strict) && warnings > 0);
      return report(ctx, issues, fileCount(folder.files), formatOf(ctx, i.format), failed, ctx.version);
    },
  }),
  defineCommand({
    ...common,
    id: "course.fmt",
    summary: "Format a course folder: canonical YAML, ids, directives, numeric prefixes",
    description:
      "Rewrites the files in place: YAML keys in canonical order (comments stay), ids minted for elements without one, loose Markdown wrapped in author paragraphs, directives printed in one form, module and lesson prefixes renumbered (01-, 02-, ...), asset sha256 and size filled. Running it twice changes nothing the second time. --check changes nothing and exits 13 when files would change. Files with syntax errors stop it (exit 7); run `course validate` for the details.",
    kind: "local",
    positionals: ["dir"],
    input: z.object({ dir, check: z.boolean().optional().describe("Only report the files that would change; exit 13 when there are any.") }),
    output: z.unknown(),
    examples: [
      { title: "Format the current folder", argv: "course fmt" },
      { title: "Fail CI when the folder is not formatted", argv: "course fmt --check" },
    ],
    async run(ctx, i) {
      const folder = readFolder(i.dir ?? ".");
      const plan = planFormat(folder.files, { mint: mintId, assets: folder.assets });
      if (plan.blocking.length > 0) return report(ctx, plan.blocking, fileCount(folder.files), formatOf(ctx, undefined), true, ctx.version);
      const changed = changedPaths(folder.files, plan);
      if (i.check) return { data: { check: true, changed }, ...(changed.length > 0 ? { exitCode: 13 } : {}) };
      applyPlanToDisk(folder.root, plan);
      return { data: { check: false, changed } };
    },
  }),
];
