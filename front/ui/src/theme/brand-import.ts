/**
 * Brand importer: turns a pasted brand description into a tenant theme proposal, without an agent.
 *
 * Accepted input (any mix, one string):
 *   - JSON: { "primary": "#c2552d", "background": "rgb(20,20,40)", "colors": { "text": "hsl(0 0% 10%)" } }
 *   - CSS custom properties: `--brand-primary: #c2552d; --bg: #fff;`
 *   - colours from a URL query or plain text: `?primary=%23c2552d&bg=ffffff`, `#c2552d #0b0f14`
 *
 * The tenant settings hold a preset name (`theme.theme`) and an accent (`theme.accent`), so the
 * proposal picks the closest preset (same light or dark mood, nearest accent hue) and the brand
 * colour as the accent, then reports the contrast the page will really get. The site darkens or
 * lightens an accent that is too weak (see front/web/src/lib/accent.ts); the checks say by how much,
 * so an admin sees what changes before approving the diff.
 */
import { adjustAccent, contrast, parseHex, toHex, type Rgb } from "./contrast.ts";
import { THEME_PRESETS, type TenantThemeName } from "./presets.ts";

export type BrandRole = "accent" | "background" | "text";

export interface BrandColour {
  name: string;
  hex: string;
}

export interface BrandCheck {
  label: string;
  ratio: number;
  required: number;
  pass: boolean;
}

export interface BrandProposal {
  theme: TenantThemeName;
  /** The brand colour as it goes into `theme.accent`. */
  accent: string;
  /** The accent as it will render for text on the theme background. */
  renderedAccent: string;
  checks: BrandCheck[];
  notes: string[];
  /** `ulams theme set` / settings body for this proposal. */
  settings: { theme: { theme: TenantThemeName; accent: string } };
  /** Colours found in the input, for the admin to see what was read. */
  colours: BrandColour[];
}

const clamp = (n: number) => Math.max(0, Math.min(255, n));

function hslToRgb(h: number, s: number, l: number): Rgb {
  const a = s * Math.min(l, 1 - l);
  const f = (n: number) => {
    const k = (n + h / 30) % 12;
    return l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1));
  };
  return [f(0) * 255, f(8) * 255, f(4) * 255];
}

/** One colour value (hex, rgb(), hsl(), or a bare hex) as RGB, null when it is not a colour. */
export function parseColour(value: string): Rgb | null {
  const text = value.trim().toLowerCase();
  const hex8 = text.match(/^#?([0-9a-f]{6})[0-9a-f]{2}$/);
  if (hex8) return parseHex(hex8[1]!);
  // a bare hex is never taken for a colour here: words like "decade" are valid hex digits
  if (text.startsWith("#")) return parseHex(text);
  const rgb = text.match(/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/);
  if (rgb) return [clamp(+rgb[1]!), clamp(+rgb[2]!), clamp(+rgb[3]!)].map(Math.round) as Rgb;
  const hsl = text.match(/^hsla?\(\s*([\d.]+)(?:deg)?[\s,]+([\d.]+)%[\s,]+([\d.]+)%/);
  if (hsl) return hslToRgb(+hsl[1]! % 360, Math.min(100, +hsl[2]!) / 100, Math.min(100, +hsl[3]!) / 100).map(Math.round) as Rgb;
  return null;
}

const VALUE = "(?:#[0-9a-fA-F]{3,8}\\b|rgba?\\([^)]*\\)|hsla?\\([^)]*\\))";

function fromJson(input: string): BrandColour[] | null {
  let data: unknown;
  try {
    data = JSON.parse(input);
  } catch {
    return null;
  }
  if (typeof data !== "object" || data === null) return null;
  const out: BrandColour[] = [];
  const walk = (node: unknown, path: string, depth: number) => {
    if (depth > 4 || node === null) return;
    if (typeof node === "string") {
      const rgb = parseColour(node);
      if (rgb) out.push({ name: path, hex: toHex(rgb) });
    } else if (typeof node === "object") {
      for (const [key, value] of Object.entries(node as Record<string, unknown>)) walk(value, path ? `${path}.${key}` : key, depth + 1);
    }
  };
  walk(data, "", 0);
  return out;
}

/** Every colour in the input with the name it was given (`primary`, `--brand-bg`, or `colour N`). */
export function extractColours(input: string): BrandColour[] {
  const json = fromJson(input.trim());
  if (json) return json;
  const found: BrandColour[] = [];
  const seen = new Set<string>();
  const add = (name: string, raw: string) => {
    const rgb = parseColour(raw);
    if (!rgb) return;
    const key = `${name}|${toHex(rgb)}`;
    if (seen.has(key)) return;
    seen.add(key);
    found.push({ name, hex: toHex(rgb) });
  };
  // `--name: #hex`, `name: rgb(...)`, `name=%23hex` and `name=ffffff` (bare hex only after `=`, as in a URL query)
  const named = new RegExp(`([\\w-]+)\\s*(?::\\s*(${VALUE})|=\\s*(?:(${VALUE})|%23([0-9a-fA-F]{3,8})\\b|([0-9a-fA-F]{6})\\b))`, "g");
  for (const m of input.matchAll(named)) {
    const value = m[2] ?? m[3] ?? (m[4] ? `#${m[4]}` : m[5] ? `#${m[5]}` : "");
    add(m[1]!, value);
  }
  // colours with no name
  let n = 0;
  for (const m of input.matchAll(new RegExp(VALUE, "g"))) {
    const before = [...found].some((f) => f.hex === toHex(parseColour(m[0])!));
    if (!before) add(`colour ${++n}`, m[0]);
  }
  return found;
}

const ROLE_WORDS: Record<BrandRole, RegExp[]> = {
  accent: [/primary/, /brand/, /accent/, /main/, /highlight/, /cta|button/],
  background: [/background|(^|[^a-z])bg([^a-z]|$)|page|canvas|surface/],
  text: [/text|foreground|(^|[^a-z])fg([^a-z]|$)|ink|body/],
};

function pick(colours: BrandColour[], role: BrandRole): BrandColour | undefined {
  for (const word of ROLE_WORDS[role]) {
    const hit = colours.find((c) => word.test(c.name.toLowerCase()) && (role !== "accent" || !/background|bg|text|border|muted/.test(c.name.toLowerCase())));
    if (hit) return hit;
  }
  return undefined;
}

function saturation([r, g, b]: Rgb): number {
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  return max === 0 ? 0 : (max - min) / max;
}

function hue([r, g, b]: Rgb): number {
  const max = Math.max(r, g, b);
  const d = max - Math.min(r, g, b);
  if (d === 0) return 0;
  const h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
  return (h * 60 + 360) % 360;
}

const hueGap = (a: Rgb, b: Rgb) => Math.min(Math.abs(hue(a) - hue(b)), 360 - Math.abs(hue(a) - hue(b)));
const luminanceOf = (rgb: Rgb) => contrast(rgb, [0, 0, 0]) / 21;

/**
 * Proposes a tenant theme from pasted brand input. Returns null when no usable accent colour is
 * in it (nothing coloured enough to be a brand colour).
 */
export function proposeBrandTheme(input: string): BrandProposal | null {
  const colours = extractColours(input);
  const notes: string[] = [];
  const named = pick(colours, "accent");
  const accentColour = named ?? [...colours].filter((c) => saturation(parseColour(c.hex)!) > 0.25).sort((a, b) => saturation(parseColour(b.hex)!) - saturation(parseColour(a.hex)!))[0];
  if (!accentColour) return null;
  const accentRgb = parseColour(accentColour.hex)!;
  notes.push(`Accent ${accentColour.hex} read from "${accentColour.name}".`);

  const bgColour = pick(colours, "background");
  const textColour = pick(colours, "text");
  const bgRgb = bgColour ? parseColour(bgColour.hex)! : null;
  const textRgb = textColour ? parseColour(textColour.hex)! : null;
  // mood: the brand background when given, else the opposite of the text colour, else light
  const dark = bgRgb ? luminanceOf(bgRgb) < 0.5 : textRgb ? luminanceOf(textRgb) > 0.5 : false;
  if (!bgRgb && !textRgb) notes.push("No background or text colour found: a light theme is assumed.");

  const family = (Object.entries(THEME_PRESETS) as Array<[TenantThemeName, (typeof THEME_PRESETS)[TenantThemeName]]>).filter(([, p]) => p.dark === dark);
  const theme = family
    .map(([name, p]) => ({ name, gap: hueGap(accentRgb, parseColour(p.accent)!) + (bgRgb ? Math.abs(luminanceOf(bgRgb) - luminanceOf(parseColour(p.bg)!)) * 90 : 0) }))
    .sort((a, b) => a.gap - b.gap)[0]!.name;
  notes.push(`Closest preset: ${THEME_PRESETS[theme].label} (${dark ? "dark" : "light"}).`);

  const preset = THEME_PRESETS[theme];
  const adjusted = adjustAccent(accentColour.hex, preset.bg)!;
  const checks: BrandCheck[] = [];
  const bgPreset = parseColour(preset.bg)!;
  checks.push({ label: `Brand accent on the ${preset.label} background`, ratio: round(contrast(accentRgb, bgPreset)), required: 5, pass: contrast(accentRgb, bgPreset) >= 5 });
  checks.push({ label: "Accent as rendered (after the automatic adjustment)", ratio: round(contrast(parseColour(adjusted.value)!, bgPreset)), required: 4.5, pass: contrast(parseColour(adjusted.value)!, bgPreset) >= 4.5 });
  if (adjusted.adjusted) notes.push(`The accent is too weak for text on this background: it renders as ${adjusted.value}. Pick a darker brand colour to keep it exactly.`);
  if (bgRgb && textRgb) checks.push({ label: "Brand text on brand background", ratio: round(contrast(textRgb, bgRgb)), required: 4.5, pass: contrast(textRgb, bgRgb) >= 4.5 });
  if (bgRgb) notes.push("Tenant themes use the preset's own page colours: the brand background only chooses light or dark.");

  return { theme, accent: accentColour.hex, renderedAccent: adjusted.value, checks, notes, settings: { theme: { theme, accent: accentColour.hex } }, colours };
}

const round = (n: number) => Math.round(n * 100) / 100;

export interface ThemeChange {
  field: "theme.theme" | "theme.accent";
  from: string | null;
  to: string;
}

/** What approving the proposal changes, against the tenant's current settings (unchanged fields are left out). */
export function themeDiff(proposal: BrandProposal, current: { theme?: string; accent?: string } | null | undefined): ThemeChange[] {
  const changes: ThemeChange[] = [];
  if ((current?.theme ?? null) !== proposal.theme) changes.push({ field: "theme.theme", from: current?.theme ?? null, to: proposal.theme });
  if ((current?.accent?.toLowerCase() ?? null) !== proposal.accent.toLowerCase()) changes.push({ field: "theme.accent", from: current?.accent ?? null, to: proposal.accent });
  return changes;
}
