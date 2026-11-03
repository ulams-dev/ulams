/**
 * Schema.org `Course` structured data for a course page (JSON-LD), built only from what the API returned:
 * the tenant is the `provider`, `hasCourseInstance` says it is online and self-paced, and `offers` is there
 * only when the course has a price above zero (a free course carries none, so a crawler never reads a price
 * the page does not show). Fields the course does not have are left out, never invented.
 */
import type { CourseModel } from "./view-model.ts";

export interface CourseJsonLdInput {
  course: CourseModel;
  /** The tenant's name (company name), the provider. */
  tenantName: string;
  /** Public origin of the site, without a path (`https://coffee.example`). */
  origin: string;
  /** BCP 47 language of the course content. */
  language: string;
}

/** ISO 8601 duration of the course's workload, from `hours_to_complete`, else from the lessons' minutes. */
export function workload(course: Pick<CourseModel, "hours" | "minutes">): string | undefined {
  if (course.hours && course.hours > 0) {
    const hours = Math.floor(course.hours);
    const minutes = Math.round((course.hours - hours) * 60);
    return `PT${hours ? `${hours}H` : ""}${minutes ? `${minutes}M` : ""}`;
  }
  return course.minutes > 0 ? `PT${Math.round(course.minutes)}M` : undefined;
}

const stripMarkdown = (text: string): string =>
  text
    .replace(/```[\s\S]*?```/g, " ")
    .replace(/!\[[^\]]*\]\([^)]*\)/g, " ")
    .replace(/\[([^\]]*)\]\([^)]*\)/g, "$1")
    .replace(/[#>*_`~]/g, "")
    .replace(/\s+/g, " ")
    .trim();

/** The description a crawler reads: the summary, else the start of the long description, else the title. */
export function courseDescription(course: Pick<CourseModel, "summary" | "description" | "title">): string {
  const text = course.summary ?? (course.description ? stripMarkdown(course.description) : "");
  const trimmed = text.length > 300 ? `${text.slice(0, 297).trimEnd()}…` : text;
  return trimmed || course.title;
}

export function courseJsonLd({ course, tenantName, origin, language }: CourseJsonLdInput): Record<string, unknown> {
  const base = origin.replace(/\/+$/, "");
  const url = `${base}${course.href}`;
  const hours = workload(course);
  const data: Record<string, unknown> = {
    "@context": "https://schema.org",
    "@type": "Course",
    "@id": `${url}#course`,
    name: course.title,
    description: courseDescription(course),
    url,
    inLanguage: language,
    provider: { "@type": "Organization", name: tenantName, url: `${base}/` },
    hasCourseInstance: {
      "@type": "CourseInstance",
      courseMode: ["online", "self-paced"],
      eventAttendanceMode: "https://schema.org/OnlineEventAttendanceMode",
      ...(hours ? { courseWorkload: hours } : {}),
    },
  };
  if (course.image?.src) data.image = new URL(course.image.src, `${base}/`).href;
  if (course.level) data.educationalLevel = course.level;
  if (course.priceMinor && course.priceMinor > 0 && course.currency) {
    data.offers = {
      "@type": "Offer",
      price: (course.priceMinor / 100).toFixed(2),
      priceCurrency: course.currency.toUpperCase(),
      category: "Paid",
      availability: "https://schema.org/InStock",
      url,
    };
  }
  return data;
}
