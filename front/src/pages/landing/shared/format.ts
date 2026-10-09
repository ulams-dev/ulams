/** Formats an API price (minor units, e.g. 8900 = 89.00) as currency. */
export function formatMoney(
  minorUnits: number | null | undefined,
  currency = "EUR",
  locale = "en-GB"
): string | null {
  if (minorUnits === null || minorUnits === undefined || isNaN(minorUnits)) {
    return null;
  }
  const value = minorUnits / 100;
  try {
    return new Intl.NumberFormat(locale, {
      style: "currency",
      currency,
      maximumFractionDigits: Number.isInteger(value) ? 0 : 2,
    }).format(value);
  } catch {
    return `${value} ${currency}`;
  }
}

/** "14 Nov" style day + month, or null for a missing/invalid date. */
export function formatDayMonth(
  iso: string | null | undefined,
  locale = "en-GB"
): string | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (isNaN(date.getTime())) return null;
  return date.toLocaleDateString(locale, { day: "numeric", month: "short" });
}

/** "18:00" style local time, or null. */
export function formatTime(
  iso: string | null | undefined,
  locale = "en-GB"
): string | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (isNaN(date.getTime())) return null;
  return date.toLocaleTimeString(locale, { hour: "2-digit", minute: "2-digit" });
}

const ROMAN: Array<[number, string]> = [
  [10, "X"],
  [9, "IX"],
  [5, "V"],
  [4, "IV"],
  [1, "I"],
];

export function toRoman(n: number): string {
  let rest = Math.max(0, Math.floor(n));
  let out = "";
  for (const [value, numeral] of ROMAN) {
    while (rest >= value) {
      out += numeral;
      rest -= value;
    }
  }
  return out;
}

/** Topic duration strings from the API ("12", "00:12:00", "12 min") to minutes. */
export function durationToMinutes(value: string | number | null | undefined): number {
  if (value === null || value === undefined || value === "") return 0;
  if (typeof value === "number") return value;
  const parts = value.split(":").map((p) => parseInt(p, 10));
  if (parts.length === 3 && parts.every((p) => !isNaN(p))) {
    return parts[0] * 60 + parts[1] + Math.round(parts[2] / 60);
  }
  if (parts.length === 2 && parts.every((p) => !isNaN(p))) {
    return parts[0] * 60 + parts[1];
  }
  const n = parseInt(value, 10);
  return isNaN(n) ? 0 : n;
}

export function formatMinutes(minutes: number): string {
  if (minutes < 60) return `${minutes} min`;
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m ? `${h} h ${m} min` : `${h} h`;
}
