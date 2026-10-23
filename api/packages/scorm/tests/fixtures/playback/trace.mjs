// Trace normaliser and golden-file helper for the SCORM playback tests (MIT, written for ulams).
//
// A trace is plain JSON: the runtime calls the SCO made (with return value and error code), the
// requests the player sent to the LMS, and the final CMI data. Before it is compared with the
// golden file it is normalised so a run on another day, port or machine gives the same text:
//   - object keys are sorted;
//   - ISO 8601 dates and times become "<date>";
//   - durations lose their fractions of a second (PT1M30.5S -> PT1M30S, 0000:01:30.55 -> 0000:01:30);
//   - origins listed in `origins` (the test server) become "<origin>";
//   - strings longer than 64 characters (suspend_data) become "<N chars sha256:xxxxxxxxxxxx>", so a
//     golden file stays readable and still changes when one character of the data changes.
// Golden files live in golden/<player>/<fixture>.json. UPDATE_GOLDEN=1 rewrites them; a changed
// golden file needs a reason in the pull request description (docs/decisions/0197).
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { isDeepStrictEqual } from 'node:util';

const here = dirname(fileURLToPath(import.meta.url));
export const goldenDir = join(here, 'golden');

const ISO_DATE = /\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?/g;
const ISO_DURATION = /^P(?:\d+Y)?(?:\d+M)?(?:\d+D)?(?:T(?:\d+H)?(?:\d+M)?(?:\d+(?:\.\d+)?S)?)?$/;
const CLOCK_DURATION = /^(\d{2,4}):(\d{2}):(\d{2})(?:\.\d+)?$/;
export const MAX_STRING = 64;

/** @param {string} text @param {string[]} origins */
export function normaliseString(text, origins = []) {
  let out = text;
  for (const origin of origins) out = out.split(origin).join('<origin>');
  if (ISO_DURATION.test(out) && out !== 'P' && out !== 'PT') {
    return out.replace(/(\d+)\.\d+S$/, '$1S');
  }
  const clock = CLOCK_DURATION.exec(out);
  if (clock) return `${clock[1]}:${clock[2]}:${clock[3]}`;
  out = out.replace(ISO_DATE, '<date>');
  if (out.length > MAX_STRING) {
    return `<${out.length} chars sha256:${createHash('sha256').update(out).digest('hex').slice(0, 12)}>`;
  }
  return out;
}

/** @param {unknown} value @param {{origins?: string[]}} [options] */
export function normalise(value, options = {}) {
  if (typeof value === 'string') return normaliseString(value, options.origins);
  if (Array.isArray(value)) return value.map((item) => normalise(item, options));
  if (value && typeof value === 'object') {
    return Object.fromEntries(
      Object.keys(value)
        .sort()
        .map((key) => [key, normalise(value[key], options)]),
    );
  }
  return value;
}

export function goldenPath(player, fixture) {
  return join(goldenDir, player, `${fixture}.json`);
}

/**
 * Compare a normalised trace with its golden file. Returns { ok, written, message }.
 * With UPDATE_GOLDEN=1 (or `update: true`) the golden file is written instead.
 */
export function matchGolden(player, fixture, actual, { update = process.env.UPDATE_GOLDEN === '1' } = {}) {
  const file = goldenPath(player, fixture);
  const text = `${JSON.stringify(actual, null, 2)}\n`;
  if (update) {
    mkdirSync(dirname(file), { recursive: true });
    writeFileSync(file, text);
    return { ok: true, written: true, message: `wrote ${file}` };
  }
  if (!existsSync(file)) {
    return { ok: false, written: false, message: `missing golden file ${file}; run with UPDATE_GOLDEN=1 and explain the change in the PR` };
  }
  const expected = JSON.parse(readFileSync(file, 'utf8'));
  if (isDeepStrictEqual(expected, actual)) return { ok: true, written: false, message: 'match' };
  return { ok: false, written: false, message: `trace differs from ${file}\n${firstDifference(expected, actual)}` };
}

/** A short path-and-values description of the first difference between two JSON values. */
export function firstDifference(expected, actual, path = '$') {
  if (isDeepStrictEqual(expected, actual)) return '';
  const bothObjects = expected && actual && typeof expected === 'object' && typeof actual === 'object';
  if (bothObjects && Array.isArray(expected) === Array.isArray(actual)) {
    const keys = [...new Set([...Object.keys(expected), ...Object.keys(actual)])];
    for (const key of keys) {
      const found = firstDifference(expected[key], actual[key], Array.isArray(expected) ? `${path}[${key}]` : `${path}.${key}`);
      if (found) return found;
    }
  }
  return `${path}: expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`;
}
