#!/usr/bin/env node
// Optional Cloudflare WAF custom rule for the `ulams.app` zone (ADR 0193, IMG-6b): block every /cdn-cgi/image/* request whose
// option string is not one that the image URL builder emits, so nobody can use the free Image Transformations quota
// (5,000 unique transformations a month) with arbitrary widths. The list comes from `allowedCloudflareOptions()` in
// front/sdk/src/images.ts, so the rule follows the presets; regenerate and replace the rule whenever the presets change.
//
//   node --experimental-strip-types --no-warnings deploy/mydevil/cloudflare/image-waf-rule.mjs [--expression | --rule | --list]
//
// --expression  the rule expression only (paste into the dashboard's expression editor)
// --rule        the rule as JSON for the rulesets API (default)
// --list        the allowed option strings, one per line
// Prints only; it changes nothing on Cloudflare. The operator steps are in deploy/mydevil/STAGING-NOTES.md.
// The Free plan has no regex in custom rules, so the expression is a list of `starts_with` on the full prefix.
import { allowedCloudflareOptions } from "../../../front/sdk/src/images.ts";

export const RULE_DESCRIPTION = "ulams: block /cdn-cgi/image options the builder never emits (ADR 0193)";
/** Cloudflare's limit for one rule expression. */
export const MAX_EXPRESSION_LENGTH = 4096;

export function allowedPrefixes() {
  return allowedCloudflareOptions().map((options) => `/cdn-cgi/image/${options}/`);
}

export function ruleExpression(prefixes = allowedPrefixes()) {
  const allowed = prefixes.map((prefix) => `starts_with(http.request.uri.path, "${prefix}")`).join(" or ");
  return `(starts_with(http.request.uri.path, "/cdn-cgi/image/") and not (${allowed}))`;
}

export function rule() {
  const expression = ruleExpression();
  if (expression.length > MAX_EXPRESSION_LENGTH) {
    throw new Error(`rule expression is ${expression.length} characters; Cloudflare allows ${MAX_EXPRESSION_LENGTH}`);
  }
  return { action: "block", description: RULE_DESCRIPTION, enabled: true, expression };
}

if (process.argv[1] && import.meta.url === new URL(`file://${process.argv[1]}`).href) {
  const mode = process.argv[2] ?? "--rule";
  if (mode === "--list") console.log(allowedCloudflareOptions().join("\n"));
  else if (mode === "--expression") console.log(rule().expression);
  else if (mode === "--rule") console.log(JSON.stringify(rule(), null, 2));
  else {
    console.error("usage: image-waf-rule.mjs [--expression | --rule | --list]");
    process.exit(2);
  }
}
