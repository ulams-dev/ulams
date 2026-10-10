// The lesson-page side of the tests: a minimal host that plays a package in an opaque-origin sandbox
// and speaks ulams-ix v1 through the real bridge library. It records every message it accepts in
// window.__log; tests drive it through window.__host. Query parameters set the `init` message.
import { createHost, newNonce, type Chrome, type Display, type InitPayload } from "../../../front/interactive-bridge/src/index.ts";

const q = new URLSearchParams(location.search);
const frame = document.getElementById("frame") as HTMLIFrameElement;
const w = window as unknown as Record<string, unknown>;
const log: Array<Record<string, unknown>> = [];
w.__log = log;
w.__timedOut = false;

const init: InitPayload = {
  locale: q.get("locale") ?? "en",
  theme: { "--ulams-color-bg": "#ffffff" },
  reducedMotion: q.get("reducedMotion") === "1",
  display: (q.get("display") ?? "inline") as Display,
  chrome: (q.get("chrome") ?? "none") as Chrome,
  ...(q.get("showcase") === "1" ? { showcase: true } : {}),
  ...(q.get("startStep") ? { startStep: q.get("startStep") as string } : {}),
  ...(q.get("from") || q.get("to") ? { range: { ...(q.get("from") ? { from: q.get("from") as string } : {}), ...(q.get("to") ? { to: q.get("to") as string } : {}) } } : {}),
};

w.__host = createHost(frame, {
  nonce: newNonce(),
  init,
  readyTimeoutMs: Number(q.get("timeout") ?? 10_000),
  onMessage: (m) => log.push(m as unknown as Record<string, unknown>),
  onTimeout: () => {
    w.__timedOut = true;
  },
});
frame.src = q.get("src") as string;
