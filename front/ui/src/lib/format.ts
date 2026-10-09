/** Formatting helpers shared by catalogue components (pure, server and browser). */

export const FORMAT_LABELS: Record<string, string> = {
  video: "Video",
  audio: "Audio",
  reading: "Reading",
  image: "Image",
  pdf: "PDF",
  embed: "Embed",
  interactive: "Interactive",
  scorm: "Simulation",
  tracked: "Tracked activity",
  quiz: "Quiz",
  project: "Project",
};

export const formatLabel = (format: string): string => FORMAT_LABELS[format] ?? format;

export function formatMinutes(minutes: number): string {
  if (!minutes || minutes < 0) return "";
  if (minutes < 60) return `${minutes} min`;
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m ? `${h} h ${m} min` : `${h} h`;
}

const ROMAN: Array<[number, string]> = [
  [50, "L"],
  [40, "XL"],
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

/** "14 Nov" + "18:00 CET"-style parts for an ISO date, in the given time zone. */
export function formatEventDate(
  iso: string | undefined,
  options: { locale?: string; timeZone?: string } = {}
): { day: string; time: string; full: string } | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  const locale = options.locale ?? "en-GB";
  const timeZone = options.timeZone ?? "Europe/Warsaw";
  const day = new Intl.DateTimeFormat(locale, { day: "numeric", month: "short", timeZone }).format(date);
  // UTC is used for wall-clock times without a zone (stationary events): no zone label then.
  const time = new Intl.DateTimeFormat(locale, {
    hour: "2-digit",
    minute: "2-digit",
    timeZone,
    ...(timeZone === "UTC" ? {} : { timeZoneName: "short" as const }),
  }).format(date);
  const full = new Intl.DateTimeFormat(locale, { weekday: "long", day: "numeric", month: "long", year: "numeric", timeZone }).format(date);
  return { day, time, full };
}
