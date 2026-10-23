/* eslint-disable @typescript-eslint/no-explicit-any */
// Shared by the playback specs. The trace helper is an ES module; the specs are compiled to CommonJS
// (front has no "type": "module"), so it is loaded with a native dynamic import.
import { resolve } from "node:path";
import { pathToFileURL } from "node:url";

const traceModule = resolve(__dirname, "../../../api/packages/scorm/tests/fixtures/playback/trace.mjs");
const nativeImport = new Function("url", "return import(url)") as (url: string) => Promise<any>;

export type TraceHelpers = {
  matchGolden: (player: string, fixture: string, actual: unknown) => { ok: boolean; message: string };
  normalise: (value: unknown, options?: { origins?: string[] }) => unknown;
};

export const loadTrace = (): Promise<TraceHelpers> => nativeImport(pathToFileURL(traceModule).href);

// scorm-again settings whose defaults decide behaviour, pinned in every golden trace.
export const PINNED_SETTINGS = [
  "autocommit", "autocommitSeconds", "asyncCommit", "sendFullCommit", "lmsCommitUrl", "dataCommitFormat",
  "commitRequestDataType", "autoProgress", "selfReportSessionTime", "alwaysSendTotalTime",
  "renderCommonCommitFields", "strict_errors", "mastery_override",
];

/**
 * Runs in the page: the state of the SCORM API after a session. `commitObject` is what scorm-again would
 * send to a commit endpoint (status derivation, score, total time), rendered locally.
 */
export function collectApiState(pinned: string[]) {
  const w = window as any;
  const api = w.API_1484_11 ?? w.API;
  const settings: Record<string, unknown> = {};
  for (const key of pinned) settings[key] = api.settings && key in api.settings ? api.settings[key] : null;
  // scorm-again 3 replaced asyncCommit by useAsynchronousCommits + throttleCommits. Report the 2.6 name:
  // false = commit sent at once with fetch, true = batched for 500 ms, "synchronous" = XMLHttpRequest (3 default).
  if (api.settings && "useAsynchronousCommits" in api.settings) {
    settings.asyncCommit = api.settings.useAsynchronousCommits ? api.settings.throttleCommits : "synchronous";
  }
  const render = (terminate: boolean) => {
    try {
      return JSON.parse(JSON.stringify(api.renderCommitObject(terminate)));
    } catch (error) {
      return { renderError: String((error as Error).message) };
    }
  };
  return {
    version: w.API_1484_11 ? "2004" : "1.2",
    commitObject: render(false),
    terminateCommitObject: render(true),
    finalCmi: JSON.parse(JSON.stringify(api.cmi)),
    settings,
  };
}

/** Wait until `count()` stops growing: scorm-again 2.6 fires some commit events after the SCO has finished. */
export async function settle(count: () => Promise<number>, quietMs = 600, maxMs = 8000) {
  const start = Date.now();
  let last = await count();
  let stableSince = Date.now();
  while (Date.now() - start < maxMs) {
    await new Promise((resolve) => setTimeout(resolve, 100));
    const now = await count();
    if (now !== last) {
      last = now;
      stableSince = Date.now();
    } else if (Date.now() - stableSince >= quietMs) {
      return;
    }
  }
}

// The explicit 2.6-behaviour settings (front/src/lib/scorm-player/settings.ts, content-player/player.js) reach
// scorm-again; scorm-again 2 ignores the keys it does not know, scorm-again 3 needs them.
export const LEGACY_EXPECTED = {
  mastery_override: false,
  useAsynchronousCommits: true,
  throttleCommits: false,
  strict_errors: true,
  autoCompleteLessonStatus: false,
  accumulateSessionTimeOnTerminate: false,
};

export function readLegacySettings(expected: Record<string, unknown>) {
  const w = window as any;
  const api = w.API_1484_11 ?? w.API;
  return Object.fromEntries(Object.keys(expected).map((key) => [key, api.settings[key]]));
}
