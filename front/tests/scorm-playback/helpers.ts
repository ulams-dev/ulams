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
