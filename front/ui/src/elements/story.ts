/**
 * Shared timeline player for the landing stories (<ulams-story>) and the tabbed workflow
 * showcase (<ulams-workflows>). Elements carry their schedule as attributes (milliseconds):
 *
 *   data-at      the element gets class `on` from this time
 *   data-until   `on` is removed again at this time
 *   data-at2     the element gets class `on2` from this time (a second state, e.g. a cursor move)
 *   data-type    typed text: the element's text is typed over this many ms from `data-at`
 *   data-count   "from,to,ms[,decimals]": a number that counts up from `data-at` (prefix in data-prefix)
 *
 * Only classes and text change, so CSS decides what moves (transforms and opacity only). The
 * player renders a pure function of time, so the final frame (reduced motion, no JS) is `render(total)`.
 */

export const isOn = (t: number, at: number, until = Infinity): boolean => t >= at && t < until;

/** How many characters of a typed text are visible at time t. */
export function typedLength(t: number, at: number, dur: number, length: number): number {
  if (t < at) return 0;
  if (dur <= 0 || t >= at + dur) return length;
  return Math.floor(((t - at) / dur) * length);
}

/** The value of a counter at time t, rounded to `decimals`. */
export function countAt(t: number, at: number, dur: number, from: number, to: number, decimals = 2): number {
  const p = t <= at ? 0 : dur <= 0 || t >= at + dur ? 1 : (t - at) / dur;
  return Number((from + (to - from) * p).toFixed(decimals));
}

interface Item {
  el: HTMLElement;
  at: number;
  until: number;
  at2: number;
  on: boolean;
  on2: boolean;
  type?: { dur: number; len: number; full: string; shown: Text; rest: Text; n: number };
  count?: { from: number; to: number; dur: number; decimals: number; prefix: string; last: number };
}

export interface PlayerOptions {
  reduced?: boolean;
  loop?: boolean;
  onEnd?: () => void;
}

const num = (el: Element, name: string, fallback: number): number => {
  const v = el.getAttribute(name);
  return v === null || v === "" || Number.isNaN(Number(v)) ? fallback : Number(v);
};

export class StoryPlayer {
  readonly total: number;
  readonly hold: number;
  t = 0;
  /** True once the end was reached (non-looping players) or the final frame is shown. */
  ended = false;
  visible = false;
  docVisible = true;
  userPaused = false;
  hostPaused = false;
  private items: Item[] = [];
  private raf = 0;
  private last = 0;
  private readonly reduced: boolean;
  private readonly loop: boolean;
  private readonly onEnd?: () => void;

  constructor(
    readonly root: HTMLElement,
    opts: PlayerOptions = {}
  ) {
    this.reduced = Boolean(opts.reduced);
    this.loop = Boolean(opts.loop);
    this.onEnd = opts.onEnd;
    this.total = num(root, "data-total", 0);
    this.hold = num(root, "data-hold", 2500);
    for (const el of root.querySelectorAll<HTMLElement>("[data-at]")) this.items.push(this.parse(el));
    if (this.reduced) {
      root.setAttribute("data-static", "");
      this.showFinal();
    } else {
      this.render(0);
    }
  }

  private parse(el: HTMLElement): Item {
    const item: Item = { el, at: num(el, "data-at", 0), until: num(el, "data-until", Infinity), at2: num(el, "data-at2", Infinity), on: false, on2: false };
    const dur = el.getAttribute("data-type");
    if (dur !== null) {
      const full = el.textContent ?? "";
      el.textContent = "";
      const cur = document.createElement("i");
      cur.className = "u-cur";
      cur.setAttribute("aria-hidden", "true");
      const shown = document.createTextNode("");
      const rest = document.createElement("span");
      rest.className = "u-rest";
      const restText = document.createTextNode(full);
      rest.append(restText);
      el.append(shown, cur, rest);
      item.type = { dur: Number(dur) || 0, len: full.length, full, shown, rest: restText, n: -1 };
    }
    const count = el.getAttribute("data-count");
    if (count) {
      const [from = 0, to = 0, dur2 = 0, decimals = 2] = count.split(",").map(Number);
      item.count = { from, to, dur: dur2, decimals, prefix: el.getAttribute("data-prefix") ?? "", last: NaN };
    }
    return item;
  }

  render(t: number): void {
    for (const it of this.items) {
      const on = isOn(t, it.at, it.until);
      if (on !== it.on) {
        it.on = on;
        it.el.classList.toggle("on", on);
      }
      const on2 = t >= it.at2;
      if (on2 !== it.on2) {
        it.on2 = on2;
        it.el.classList.toggle("on2", on2);
      }
      if (it.type) {
        const full = it.type.full;
        const n = typedLength(t, it.at, it.type.dur, it.type.len);
        if (n !== it.type.n) {
          it.type.n = n;
          it.type.shown.data = full.slice(0, n);
          it.type.rest.data = full.slice(n);
          it.el.classList.toggle("typing", n > 0 && n < it.type.len);
        }
      }
      if (it.count) {
        const v = countAt(t, it.at, it.count.dur, it.count.from, it.count.to, it.count.decimals);
        if (v !== it.count.last) {
          it.count.last = v;
          it.el.textContent = it.count.prefix + v.toFixed(it.count.decimals);
        }
      }
    }
  }

  private showFinal(): void {
    this.t = this.total;
    this.ended = true;
    this.render(this.total);
  }

  get active(): boolean {
    return !this.reduced && !this.ended && this.visible && this.docVisible && !this.userPaused && !this.hostPaused;
  }

  /** Re-evaluates whether the clock should run; call after changing any pause/visibility flag. */
  update(): void {
    if (this.active) {
      if (!this.raf && typeof requestAnimationFrame === "function") {
        this.last = 0;
        this.raf = requestAnimationFrame(this.frame);
      }
    } else if (this.raf) {
      cancelAnimationFrame(this.raf);
      this.raf = 0;
    }
  }

  private frame = (now: number): void => {
    this.raf = 0;
    const dt = this.last ? Math.min(now - this.last, 100) : 0;
    this.last = now;
    this.tick(dt);
    if (this.active && typeof requestAnimationFrame === "function") this.raf = requestAnimationFrame(this.frame);
  };

  /** Advances the clock by dt ms (the rAF loop calls this; tests call it directly). */
  tick(dt: number): void {
    if (!this.active) return;
    this.t += dt;
    if (this.t >= this.total + this.hold) {
      if (this.loop) {
        this.t = 0;
        this.render(0);
        return;
      }
      this.showFinal();
      this.onEnd?.();
      return;
    }
    this.render(Math.min(this.t, this.total));
  }

  replay(): void {
    if (this.reduced) return;
    this.ended = false;
    this.userPaused = false;
    this.t = 0;
    this.render(0);
    this.update();
  }

  reset(): void {
    this.ended = false;
    this.t = 0;
    if (this.reduced) this.showFinal();
    else this.render(0);
    this.update();
  }

  set(flags: Partial<Pick<StoryPlayer, "visible" | "docVisible" | "userPaused" | "hostPaused">>): void {
    Object.assign(this, flags);
    this.update();
  }
}

const reducedMotion = (): boolean => typeof matchMedia === "function" && matchMedia("(prefers-reduced-motion: reduce)").matches;

/** <ulams-story data-total="9400" data-hold="2500" loop> … [data-replay] [data-pause] … </ulams-story> */
export class UlamsStory extends HTMLElement {
  player!: StoryPlayer;
  private io?: IntersectionObserver;

  connectedCallback(): void {
    this.player = new StoryPlayer(this, {
      reduced: reducedMotion(),
      loop: this.hasAttribute("loop"),
      onEnd: () => this.dispatchEvent(new CustomEvent("story-end", { bubbles: true })),
    });
    if (this.hasAttribute("data-host-gated")) this.player.hostPaused = true;
    if ("IntersectionObserver" in window) {
      this.io = new IntersectionObserver(([entry]) => this.player.set({ visible: Boolean(entry?.isIntersecting) }), { rootMargin: "-20% 0px -20% 0px" });
      this.io.observe(this);
    } else {
      this.player.set({ visible: true });
    }
    document.addEventListener("visibilitychange", this.onVisibility);
    this.querySelector("[data-replay]")?.addEventListener("click", () => this.player.replay());
    const pause = this.querySelector<HTMLButtonElement>("[data-pause]");
    pause?.addEventListener("click", () => {
      const paused = !this.player.userPaused;
      this.player.set({ userPaused: paused });
      pause.setAttribute("aria-pressed", String(paused));
      pause.textContent = paused ? "Play" : "Pause";
    });
    if (this.player.ended && reducedMotion()) this.querySelectorAll("[data-replay],[data-pause]").forEach((b) => b.setAttribute("hidden", ""));
  }

  disconnectedCallback(): void {
    this.io?.disconnect();
    document.removeEventListener("visibilitychange", this.onVisibility);
    this.player.set({ visible: false });
  }

  private onVisibility = (): void => this.player.set({ docVisible: !document.hidden });
}

/**
 * <ulams-workflows dwell="3500">: roving-tabindex tabs over <ulams-story> panels. The active panel
 * plays once, then the next tab opens after a pause; hover, focus, a manual choice or the pause
 * button stop the auto-advance.
 */
export class UlamsWorkflows extends HTMLElement {
  private tabs: HTMLElement[] = [];
  private panels: HTMLElement[] = [];
  private index = 0;
  private auto = true;
  private paused = false;
  private held = false;
  private timer = 0;
  private reduced = false;

  connectedCallback(): void {
    this.reduced = reducedMotion();
    this.tabs = [...this.querySelectorAll<HTMLElement>('[role="tab"]')];
    this.panels = this.tabs.map((t) => document.getElementById(t.getAttribute("aria-controls") ?? "") as HTMLElement);
    this.tabs.forEach((tab, i) => {
      tab.addEventListener("click", () => this.select(i, true));
      tab.addEventListener("keydown", (e) => this.onKey(e as KeyboardEvent, i));
    });
    this.addEventListener("story-end", () => this.schedule());
    for (const ev of ["pointerenter", "focusin"]) this.addEventListener(ev, () => this.hold(true));
    for (const ev of ["pointerleave", "focusout"]) this.addEventListener(ev, () => this.hold(false));
    const pause = this.querySelector<HTMLButtonElement>("[data-pause]");
    if (this.reduced) pause?.setAttribute("hidden", "");
    pause?.addEventListener("click", () => {
      this.paused = !this.paused;
      pause.setAttribute("aria-pressed", String(this.paused));
      pause.textContent = this.paused ? "Play" : "Pause";
      this.stories().forEach((s) => s.player.set({ userPaused: this.paused }));
      if (this.paused) clearTimeout(this.timer);
      else if (this.story(this.index)?.player.ended) this.schedule();
    });
    customElements.whenDefined("ulams-story").then(() => this.select(0, false));
  }

  private stories = (): UlamsStory[] => this.panels.map((p) => p.querySelector("ulams-story") as UlamsStory).filter(Boolean);
  private story = (i: number): UlamsStory | null => (this.panels[i]?.querySelector("ulams-story") as UlamsStory | null) ?? null;

  private onKey(e: KeyboardEvent, i: number): void {
    const n = this.tabs.length;
    const next = { ArrowRight: (i + 1) % n, ArrowLeft: (i + n - 1) % n, Home: 0, End: n - 1 }[e.key];
    if (next === undefined) return;
    e.preventDefault();
    this.tabs[next]?.focus();
    this.select(next, true);
  }

  select(i: number, manual: boolean): void {
    if (manual) this.auto = false;
    clearTimeout(this.timer);
    this.index = i;
    this.tabs.forEach((tab, k) => {
      const on = k === i;
      tab.setAttribute("aria-selected", String(on));
      tab.tabIndex = on ? 0 : -1;
      this.panels[k]?.toggleAttribute("data-active", on);
      const s = this.story(k);
      if (!s?.player) return;
      s.player.hostPaused = !on;
      if (on) {
        s.player.userPaused = this.paused;
        s.player.reset();
        s.player.update();
      } else {
        s.player.update();
      }
    });
  }

  private hold(on: boolean): void {
    this.held = on;
    if (on) clearTimeout(this.timer);
    else if (this.story(this.index)?.player.ended) this.schedule();
  }

  private schedule(): void {
    clearTimeout(this.timer);
    if (!this.auto || this.paused || this.held || this.reduced) return;
    this.timer = window.setTimeout(() => this.select((this.index + 1) % this.tabs.length, false), Number(this.getAttribute("dwell")) || 3500);
  }
}

export function defineStories(): void {
  if (!customElements.get("ulams-story")) customElements.define("ulams-story", UlamsStory);
  if (!customElements.get("ulams-workflows")) customElements.define("ulams-workflows", UlamsWorkflows);
}
defineStories();
