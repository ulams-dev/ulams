/**
 * exec.html (ADR 0170): runs one bundle in a Worker and reports the outcome to the runner page, which
 * embeds this page in a sandboxed frame (opaque origin) for the length of one run. The content security
 * policy of this page (set by the API, see RunnerHeaders) allows no network at all, no eval and only blob
 * workers; the Worker inherits it. The harness is put in front of the bundle by the build.
 */
import { EXEC_KIND, parseToExec, type FromExec } from "./exec-protocol.ts";

declare const __HARNESS__: string;

const nonce = decodeURIComponent(location.hash.slice(1));
type Body<T> = T extends unknown ? Omit<T, "kind" | "nonce"> : never;
const send = (message: Body<FromExec>) => parent.postMessage({ kind: EXEC_KIND, nonce, ...message }, "*");

let started = false;

window.addEventListener("message", (event: MessageEvent) => {
  if (event.source !== parent || started) return;
  const message = parseToExec(event.data, nonce);
  if (!message) return;
  started = true;

  // a secret of this run, known to the harness only: the Worker's own code cannot sign a result it forges
  const token = Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) => b.toString(16).padStart(2, "0")).join("");
  const source = `${__HARNESS__.replace("__ULAMS_TOKEN__", token)}\n${message.code}\n;self.__ulamsRun();`;
  const url = URL.createObjectURL(new Blob([source], { type: "text/javascript" }));
  const worker = new Worker(url);
  const finish = () => {
    clearTimeout(timer);
    worker.terminate();
    URL.revokeObjectURL(url);
  };
  const timer = setTimeout(() => {
    finish();
    send({ type: "timeout" });
  }, message.timeoutMs);

  worker.onmessage = (e: MessageEvent) => {
    const data = e.data as Record<string, unknown> | null;
    finish();
    // the harness answers with {type: "result", token, tests, output, truncated}; anything else (a forged message) is not a result
    if (data && data.type === "result" && data.token === token && Array.isArray(data.tests) && typeof data.output === "string" && typeof data.truncated === "boolean") {
      send({ type: "result", tests: data.tests as never, output: data.output, truncated: data.truncated });
    } else {
      send({ type: "crash", message: "The test run did not report a result." });
    }
  };
  worker.onerror = (e: ErrorEvent) => {
    e.preventDefault();
    finish();
    send({ type: "crash", message: (e.message || "The code threw an error while loading.").slice(0, 500) });
  };
});

send({ type: "ready" });
