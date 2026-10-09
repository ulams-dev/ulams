/**
 * Keys of the three demo experiences (front/docs/design/experiences.md).
 *
 * The tenant's `theme.theme` setting may name a preset either by registry key
 * (`coffeeTheme`) or by short name (`coffee`); both resolve to the same experience.
 * No imports, so the function can be unit-tested with `node --test`.
 */
export const EXPERIENCE_KEYS = ["coffee", "oncall", "nightsky"] as const;

export type ExperienceKey = (typeof EXPERIENCE_KEYS)[number];

export function experienceFromThemeKey(value: unknown): ExperienceKey | null {
  if (typeof value !== "string") return null;
  const key = value.trim().replace(/theme$/i, "").toLowerCase();
  return (EXPERIENCE_KEYS as ReadonlyArray<string>).includes(key)
    ? (key as ExperienceKey)
    : null;
}
