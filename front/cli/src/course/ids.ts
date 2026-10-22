import { randomBytes } from "node:crypto";

/** Element ids are lowercase ULIDs (the server mints them; the CLI mints them for new elements). */
export const ID_RE = /^[0-9a-z]{26}$/;
export const FRAGMENT_RE = /^frg_[a-z2-7]{12}$/;
export const KEY_RE = /^[a-z0-9][a-z0-9-]{0,63}$/;

const CROCKFORD = "0123456789abcdefghjkmnpqrstvwxyz";

/** A lowercase ULID: 48 bits of time then 80 random bits, Crockford base32. */
export function mintId(now: number = Date.now(), random: Uint8Array = randomBytes(10)): string {
  let time = now;
  let head = "";
  for (let i = 0; i < 10; i++) {
    head = (CROCKFORD[time % 32] as string) + head;
    time = Math.floor(time / 32);
  }
  let bits = 0n;
  for (const byte of random) bits = (bits << 8n) | BigInt(byte);
  let tail = "";
  for (let i = 0; i < 16; i++) {
    tail = (CROCKFORD[Number(bits & 31n)] as string) + tail;
    bits >>= 5n;
  }
  return head + tail;
}

/** File and folder names: lowercase ASCII words joined by dashes. */
export function slugify(text: string): string {
  const slug = text
    .normalize("NFKD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 48)
    .replace(/-+$/, "");
  return slug || "untitled";
}

export function pad(n: number, width = 2): string {
  return String(n).padStart(width, "0");
}
