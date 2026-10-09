/**
 * Data model of the platform product landing (app.localhost): the demo academies with links
 * into their fronts (learner, auto-login on /learn) and admins (auto-login in demo mode).
 * Facts come from each tenant's API (cached); nothing is invented.
 */
import { resolveTenant } from "@ulams/sdk/tenant";
import { config } from "./config.ts";
import { getSiteModel } from "./data.ts";
import { tenantFrontUrl } from "./tenant.ts";
import { THEME_COLOR } from "./theme.ts";
import { comparisonModel } from "./comparison.ts";
import type { ThemeName } from "@ulams/ui/registry";

export interface DemoCard {
  title: string;
  text?: string;
  theme: ThemeName | "platform";
  facts: Array<{ label: string; value: string }>;
  primary: { label: string; href: string };
  secondary: { label: string; href: string };
  image?: { src: string; alt: string; width: number; height: number };
}

const STYLE: Record<string, { label: string; fallbackTitle: string; text: string }> = {
  coffee: { label: "Editorial · self-paced", fallbackTitle: "The Coffee Atlas", text: "A slow, magazine-style course from seed to cup: film, podcast, interactive diagrams, a quiz and a final project." },
  oncall: { label: "Cohort · dark pro tool", fallbackTitle: "On-Call", text: "Incident command for platform engineers: a 4-week cohort with an outage simulator, a live drill and a reviewed postmortem." },
  nightsky: { label: "Gamified · kids 10–14", fallbackTitle: "Night Sky Explorers", text: "Seven short missions to the stars with Orbi the robot guide, badges and a printable diploma." },
};

export async function platformModel(current: URL) {
  const firstRule = config.tenantHosts.split(/[,\n]+/)[0]?.split("=>")[0]?.trim() ?? "";
  const demos = await Promise.all(
    config.demoTenants.map(async (slug): Promise<DemoCard | null> => {
      const tenant = resolveTenant(firstRule.replace("{slug}", slug), { pattern: config.tenantHosts, adminUrlTemplate: config.adminUrl });
      const front = tenantFrontUrl(slug, config.tenantHosts, current);
      if (!tenant || !front) return null;
      const site = await Promise.race([
        getSiteModel(tenant).catch(() => null),
        new Promise<null>((resolve) => setTimeout(() => resolve(null), 2500)),
      ]);
      const style = STYLE[slug];
      const course = site?.course;
      const theme = (["coffee", "oncall", "nightsky"].includes(slug) ? slug : "platform") as DemoCard["theme"];
      const facts: DemoCard["facts"] = [];
      if (style) facts.push({ label: "Style", value: style.label });
      if (course) {
        facts.push({ label: "Lessons", value: String(course.lessonCount) });
        facts.push({ label: "Topics", value: String(course.topicCount) });
      }
      return {
        title: site?.tenant.name ?? style?.fallbackTitle ?? slug,
        text: style?.text ?? course?.summary,
        theme,
        facts,
        primary: { label: "Open as learner", href: course ? `${front}${course.learnHref}` : `${front}/` },
        secondary: { label: "Open as admin", href: tenant.adminUrl },
        image: course?.image ? { ...course.image, alt: "" } : undefined,
      };
    })
  );
  const list = demos.filter((d): d is DemoCard => d !== null);
  return { demos: list, demoCount: list.length, comparison: comparisonModel() };
}

export const PLATFORM_THEME_COLOR = "#fafaf9";
export { THEME_COLOR };
