import type { Course, Product, PublicSettings, StationaryEvent, UserSummary, Webinar } from "@ulams/sdk";
import coffee from "../fixtures/coffee.json";
import oncall from "../fixtures/oncall.json";
import nightsky from "../fixtures/nightsky.json";
import gravity from "../fixtures/gravity.json";
import poland from "../fixtures/poland.json";
import ulam from "../fixtures/ulam.json";
import coffeeProgram from "../fixtures/coffee-program.json";
import type { RawSiteData } from "../../src/lib/view-model.ts";

interface Fixture {
  settings: PublicSettings;
  course: Course;
  products: Product[];
  webinars: Webinar[];
  events: StationaryEvent[];
  tutors: UserSummary[];
}

/**
 * API responses from the seeded demo tenants (DemoCoursesSeeder). coffee, oncall and nightsky were captured on
 * 2026-10-08; gravity, poland and ulam hold the placeholder free course each tenant is seeded with (one welcome
 * lesson) until the real courses (M8, M9) replace them.
 */
export const FIXTURES = { coffee, oncall, nightsky, gravity, poland, ulam } as unknown as Record<"coffee" | "oncall" | "nightsky" | "gravity" | "poland" | "ulam", Fixture>;
export const COFFEE_PROGRAM = coffeeProgram as unknown as Course;

export function raw(slug: keyof typeof FIXTURES): RawSiteData {
  const f = FIXTURES[slug];
  return { settings: f.settings, courses: [f.course], course: f.course, tutors: f.tutors, webinars: f.webinars, events: f.events, products: f.products };
}
