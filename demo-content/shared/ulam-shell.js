/*! ulams demo-content ulam-shell: the step card, step navigation and bridge wiring shared by the Ulam interactives.
 *  MIT License, Copyright (c) 2026 Mateusz Wojczal. sync-bridge copies it into each package's vendor/.
 *  Plain ES2020 module. The package passes in the bridge's connect() so this file has no imports. */

/**
 * @typedef {{ id: string, title: string, text: string, poster?: string }} Step
 * @typedef {{
 *   steps: Step[], step: Step, index: number, range: { lo: number, hi: number }, chrome: "full" | "minimal" | "none",
 *   reduced: boolean, poster: boolean, showcase: boolean, embedded: boolean, lang: string, visited: Set<string>, bridge: any,
 *   goTo: (id: string) => boolean, announce: (message: string) => void, complete: () => void,
 * }} Shell
 */

/**
 * Starts the shell: loads ulams-interactive.json, connects to the lesson page, shows the first step.
 * Required markup: #ix-card (with .ix-step-no, .ix-title, .ix-text, .ix-prev, .ix-next), #ix-stage, #ix-live.
 * @param {{
 *   connect: Function,
 *   capabilities?: Record<string, unknown>,
 *   render: (step: Step, shell: Shell) => void,
 *   onInit?: (init: any, shell: Shell) => void,
 *   onPause?: () => void,
 *   onResume?: () => void,
 *   manifestUrl?: string,
 *   load?: Promise<unknown>,
 * }} options
 * `load` is the package's own data (fetched at the top of main.js): `ready` and the first step wait for it.
 * @returns {Promise<Shell>}
 */
export async function startShell(options) {
  const root = document.documentElement;
  const q = (s) => /** @type {HTMLElement} */ (document.querySelector(s));
  const params = new URLSearchParams(location.search);
  const poster = params.has("ulams-poster");
  // The landing hero (init.showcase, or ?ulams-showcase for the hero's still): no card, no controls, nothing focusable.
  let showcase = params.has("ulams-showcase");
  const embedded = window.parent !== window;
  const mq = window.matchMedia ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;

  /** @type {Shell} */
  const shell = /** @type {any} */ ({
    steps: [], step: null, index: -1, range: { lo: 0, hi: 1e9 }, chrome: poster ? "none" : "full",
    reduced: !!(mq && mq.matches) || params.has("instant"), poster, showcase, embedded, lang: "en", visited: new Set(), bridge: null,
  });
  let completed = false;
  /** @type {any} */
  let initMsg = null;
  /** @type {(v: any) => void} */
  let resolveInit = () => {};
  const initReceived = new Promise((resolve) => (resolveInit = resolve));

  const manifestPromise = Promise.all([
    fetch(options.manifestUrl || "ulams-interactive.json").then((r) => { if (!r.ok) throw new Error(`manifest ${r.status}`); return r.json(); }),
    options.load || Promise.resolve(),
  ])
    .then(([m]) => {
      shell.steps = m.steps.map((s) => ({ id: s.id, title: s.title[shell.lang] || s.title[m.defaultLocale], text: s.text[shell.lang] || s.text[m.defaultLocale], poster: s.poster }));
      shell.range.hi = shell.steps.length - 1;
    });

  shell.bridge = options.connect({
    steps: () => shell.steps.map((s) => s.id),
    whenReady: manifestPromise,
    capabilities: { steps: true, reducedMotion: true, background: false, locales: ["en"], ...(options.capabilities || {}) },
    onInit: (init) => { initMsg = init; resolveInit(init); },
    onGoToStep: (id) => { manifestPromise.then(() => { if (shell.step) shell.goTo(id); }); },
    onLocale: () => {},
    onPause: () => { root.classList.add("paused"); if (options.onPause) options.onPause(); },
    onResume: () => { root.classList.remove("paused"); if (options.onResume) options.onResume(); },
  });

  shell.announce = (message) => { const live = q("#ix-live"); live.textContent = ""; setTimeout(() => { live.textContent = message; }, 30); };
  shell.complete = () => { if (completed) return; completed = true; shell.bridge.complete(); };

  const card = q("#ix-card");
  const paint = () => {
    const i = shell.index, n = shell.steps.length;
    q(".ix-step-no").textContent = `Step ${i + 1} of ${n}`;
    q(".ix-title").textContent = shell.step.title;
    q(".ix-text").textContent = shell.step.text;
    /** @type {HTMLButtonElement} */ (q(".ix-prev")).disabled = i <= shell.range.lo;
    /** @type {HTMLButtonElement} */ (q(".ix-next")).disabled = i >= Math.min(shell.range.hi, n - 1);
  };
  shell.goTo = (id) => {
    const i = shell.steps.findIndex((s) => s.id === id);
    if (i < 0 || i < shell.range.lo || i > shell.range.hi) return false;
    const changed = i !== shell.index;
    shell.index = i; shell.step = shell.steps[i]; shell.visited.add(id);
    paint();
    options.render(shell.step, shell);
    if (changed) {
      shell.bridge.stepChanged(id);
      shell.bridge.progress(shell.steps.length > 1 ? i / (shell.steps.length - 1) : 1);
      shell.announce(`Step ${i + 1} of ${shell.steps.length}: ${shell.step.title}`);
    }
    return true;
  };
  q(".ix-prev").addEventListener("click", () => shell.goTo(shell.steps[shell.index - 1].id));
  q(".ix-next").addEventListener("click", () => shell.goTo(shell.steps[shell.index + 1].id));

  await manifestPromise;
  if (embedded) {
    const init = await initReceived;
    shell.chrome = init.chrome;
    if (init.reducedMotion) shell.reduced = true;
    if (init.showcase) showcase = shell.showcase = true;
    shell.lang = String(init.locale || "en").slice(0, 2);
    if (init.range) {
      const lo = init.range.from ? shell.steps.findIndex((s) => s.id === init.range.from) : -1;
      const hi = init.range.to ? shell.steps.findIndex((s) => s.id === init.range.to) : -1;
      shell.range.lo = lo >= 0 ? lo : 0; shell.range.hi = hi >= 0 ? hi : shell.steps.length - 1;
    }
    if (options.onInit) options.onInit(init, shell);
  }
  root.classList.add(`ix-${shell.chrome}`);
  if (shell.reduced) root.classList.add("reduced");
  if (poster) root.classList.add("ix-poster");
  if (showcase) { root.classList.add("ix-showcase"); document.body.setAttribute("inert", ""); }
  card.hidden = shell.chrome === "none" || showcase;
  const wanted = poster ? decodeURIComponent(location.hash.replace(/^#/, "")) : (initMsg && (initMsg.startStep || (initMsg.range && initMsg.range.from))) || "";
  if (!shell.goTo(wanted)) shell.goTo(shell.steps[Math.max(0, shell.range.lo)].id);
  root.dataset.ixReady = "1";
  return shell;
}
