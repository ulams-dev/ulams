/**
 * In-memory stale-while-revalidate cache for public API data.
 *
 * - fresh for `ttlMs`: served from memory;
 * - stale up to `maxStaleMs`: served from memory, refreshed in the background;
 * - older or missing: fetched (concurrent callers share one request);
 * - a failed refresh keeps serving the stale value.
 *
 * One process, one map: enough for a reference frontend. A multi-instance deployment
 * would put a shared cache (or a CDN) in front instead.
 */
export interface SwrOptions {
  ttlMs: number;
  maxStaleMs?: number;
}

interface Entry<T> {
  value: T;
  storedAt: number;
}

export class SwrCache {
  private entries = new Map<string, Entry<unknown>>();
  private inflight = new Map<string, Promise<unknown>>();
  private readonly maxEntries: number;
  private readonly now: () => number;

  constructor(options: { maxEntries?: number; now?: () => number } = {}) {
    this.maxEntries = options.maxEntries ?? 500;
    this.now = options.now ?? Date.now;
  }

  async get<T>(key: string, fetcher: () => Promise<T>, options: SwrOptions): Promise<T> {
    const entry = this.entries.get(key) as Entry<T> | undefined;
    const age = entry ? this.now() - entry.storedAt : Infinity;
    if (entry && age < options.ttlMs) return entry.value;
    if (entry && age < options.ttlMs + (options.maxStaleMs ?? 10 * 60_000)) {
      void this.refresh(key, fetcher).catch(() => undefined);
      return entry.value;
    }
    return this.refresh(key, fetcher);
  }

  /** Fetches now (deduplicated) and stores the value. */
  refresh<T>(key: string, fetcher: () => Promise<T>): Promise<T> {
    const running = this.inflight.get(key) as Promise<T> | undefined;
    if (running) return running;
    const promise = fetcher()
      .then((value) => {
        this.set(key, value);
        return value;
      })
      .finally(() => this.inflight.delete(key));
    this.inflight.set(key, promise);
    return promise;
  }

  set<T>(key: string, value: T): void {
    this.entries.delete(key);
    this.entries.set(key, { value, storedAt: this.now() });
    if (this.entries.size > this.maxEntries) {
      const oldest = this.entries.keys().next().value;
      if (oldest !== undefined) this.entries.delete(oldest);
    }
  }

  delete(prefix: string): void {
    for (const key of this.entries.keys()) if (key.startsWith(prefix)) this.entries.delete(key);
  }

  get size(): number {
    return this.entries.size;
  }
}

/** Process-wide cache shared by all requests. */
export const cache = new SwrCache();
