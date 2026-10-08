import {
  experienceFromThemeKey,
  type ExperienceKey,
} from "../../lib/components/theme/experienceKey.ts";

export type LandingChoice = ExperienceKey | "default";

/**
 * Picks the home page for the tenant: one of the experience landings when the
 * tenant's `theme.theme` setting names an experience preset, otherwise the
 * default home. Returns null while settings are still loading so that the
 * default home does not flash before a tenant landing.
 */
export function selectLanding(
  themeKey: unknown,
  settingsReady: boolean
): LandingChoice | null {
  const experience = experienceFromThemeKey(themeKey);
  if (experience) return experience;
  return settingsReady ? "default" : null;
}
