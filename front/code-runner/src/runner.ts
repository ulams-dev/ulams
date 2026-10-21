/**
 * runner.html (ADR 0170): the page the lesson embeds in a sandboxed frame (opaque origin) on the content
 * origin. It speaks `ulams-ix` with the lesson page, bundles the exercise with esbuild-wasm (loaded from
 * this origin: the page's policy allows `connect-src 'self'` for that and nothing else) and runs the bundle
 * in a second sandboxed frame, exec.html, whose policy has no network. This page never runs learner code.
 */
import { connect } from "@ulams/interactive-bridge/package";
import { EXEC_KIND, parseFromExec, type FromExec, type ToExec } from "./exec-protocol.ts";
import { createRunner, type ExecHandle } from "./runner-core.ts";

interface EsbuildGlobal {
  initialize(options: { wasmURL: string; worker: boolean }): Promise<void>;
  build(options: Record<string, unknown>): Promise<{ outputFiles?: Array<{ text: string }> }>;
}

const esbuild = (globalThis as unknown as { esbuild: EsbuildGlobal }).esbuild;

function randomNonce(): string {
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  return Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("");
}

/** A fresh exec frame for one run: sandboxed without `allow-same-origin`, no extra permissions, removed afterwards. */
function openExec(code: string, timeoutMs: number): ExecHandle {
  const nonce = randomNonce();
  const frame = document.createElement("iframe");
  frame.setAttribute("sandbox", "allow-scripts");
  frame.setAttribute("referrerpolicy", "no-referrer");
  frame.setAttribute("aria-hidden", "true");
  frame.tabIndex = -1;
  frame.style.cssText = "position:absolute;width:0;height:0;border:0;visibility:hidden";
  frame.src = `exec.html#${encodeURIComponent(nonce)}`;

  let listener: ((event: MessageEvent) => void) | null = null;
  const outcome = new Promise<Exclude<FromExec, { type: "ready" }>>((resolve) => {
    listener = (event: MessageEvent) => {
      // only the frame we made, which is opaque ("null"), and only with this run's nonce
      if (event.source !== frame.contentWindow || event.origin !== "null") return;
      const message = parseFromExec(event.data, nonce);
      if (!message) return;
      if (message.type === "ready") {
        const exec: ToExec = { kind: EXEC_KIND, nonce, type: "exec", code, timeoutMs };
        frame.contentWindow?.postMessage(exec, "*");
        return;
      }
      resolve(message);
    };
    window.addEventListener("message", listener);
  });
  document.body.appendChild(frame);
  return {
    outcome,
    close() {
      if (listener) window.removeEventListener("message", listener);
      listener = null;
      frame.remove();
    },
  };
}

const ready = esbuild.initialize({ wasmURL: "esbuild.wasm", worker: false });

const runner = createRunner({
  esbuild,
  openExec,
  now: () => performance.now(),
  setTimeout: (callback, ms) => setTimeout(callback, ms),
  clearTimeout: (handle) => clearTimeout(handle as number),
});

const bridge = connect({
  // the runner has no steps; it announces itself once esbuild is ready (or has failed: runs then answer with an error)
  steps: [],
  capabilities: {},
  whenReady: ready.catch(() => bridge.error("runner-unavailable", "The code runner could not load its build tool.")),
  onLoadExercise: (message) => runner.load({ language: message.language, tests: message.tests, timeoutMs: message.timeoutMs }),
  onRun: (message) => {
    void runner.run(message.runId, message.files).then((answer) => {
      if (answer) bridge.runResult(answer);
    });
  },
  onReset: () => runner.reset(),
});
