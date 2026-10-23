/**
 * Counting active review minutes in the browser (ADR 0194, AM-5), with no DOM so every rule is unit-tested.
 *
 * A minute counts when the tab is visible and focused and there was keyboard, pointer or scroll input in the last 120
 * seconds. Nothing identifies the person: the only identifier is a random nonce made per page load, kept in memory.
 * Several tabs of one browser elect one sender, so an author with three tabs open counts once: every tab reports its
 * active minutes to the others, and only the leader (the smallest nonce among the tabs seen recently) posts them.
 */
export const IDLE_MS = 120_000;
export const MAX_PER_CALL = 10;
export const MAX_AGE_MS = 15 * 60_000;
/** A tab that has not been heard from for this long is not a peer any more. */
export const PEER_TTL_MS = 15_000;

/** 22 characters of base64url from random bytes: unlinkable across page loads. */
export function newNonce(random: (bytes: Uint8Array) => Uint8Array = (b) => crypto.getRandomValues(b)): string {
  const bytes = random(new Uint8Array(17));
  let binary = "";
  for (const b of bytes) binary += String.fromCharCode(b);

  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "").slice(0, 22);
}

export const minuteOf = (ms: number): number => Math.floor(ms / 60_000) * 60_000;

/** The leader among the tabs heard from within the TTL (and this one): the smallest nonce. */
export function electLeader(self: string, peers: ReadonlyMap<string, number>, now: number): string {
  let leader = self;
  for (const [id, seen] of peers) if (now - seen <= PEER_TTL_MS && id < leader) leader = id;

  return leader;
}

export class ActivityTracker {
  private lastInput: number | null = null;
  private readonly pending = new Set<number>();

  /** Keyboard, pointer or scroll input. */
  input(now: number): void {
    this.lastInput = now;
  }

  /** Called every few seconds: marks the current minute when the person is active in a visible, focused tab. Returns the minute marked, or null. */
  tick(now: number, visible: boolean, focused: boolean): number | null {
    if (!visible || !focused || this.lastInput === null || now - this.lastInput > IDLE_MS) return null;
    const minute = minuteOf(now);
    this.pending.add(minute);

    return minute;
  }

  /** A minute another tab of this browser reported. */
  adopt(minute: number): void {
    this.pending.add(minute);
  }

  /** Up to 10 pending minutes, oldest first, none older than the server accepts; stale ones are dropped. They stay pending until `sent`. */
  batch(now: number): string[] {
    for (const minute of this.pending) if (now - minute > MAX_AGE_MS - 60_000) this.pending.delete(minute);

    return [...this.pending].sort((a, b) => a - b).slice(0, MAX_PER_CALL).map((m) => new Date(m).toISOString());
  }

  sent(minutes: readonly string[]): void {
    for (const iso of minutes) this.pending.delete(Date.parse(iso));
  }

  /** The server refused them (not acceptable any more): forget them rather than retry for ever. */
  drop(minutes: readonly string[]): void {
    this.sent(minutes);
  }

  get size(): number {
    return this.pending.size;
  }
}
