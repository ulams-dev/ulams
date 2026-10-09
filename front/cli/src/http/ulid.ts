const ALPHABET = "0123456789ABCDEFGHJKMNPQRSTVWXYZ";

/** A 26-character ULID (time-sortable). Uses the platform crypto only. */
export function ulid(now: number = Date.now()): string {
  let time = "";
  let t = now;
  for (let i = 0; i < 10; i++) {
    time = ALPHABET[t % 32] + time;
    t = Math.floor(t / 32);
  }
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  let random = "";
  for (let i = 0; i < 16; i++) random += ALPHABET[(bytes[i] ?? 0) % 32];
  return time + random;
}
