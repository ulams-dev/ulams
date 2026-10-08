/**
 * Turns raw API data into the data model that page documents bind to with
 * `{"$data": "/course/lessons"}`. Pure functions: no fetching, easy to test.
 * Everything here comes from the API; nothing is invented (no ratings, no counts
 * that the API does not return).
 */
import {
  durationToMinutes,
  flattenTopics,
  topicKind,
  type Course,
  type Lesson,
  type Product,
  type PublicSettings,
  type StationaryEvent,
  type Topic,
  type TopicKind,
  type TopicProgress,
  type UserSummary,
  type Webinar,
} from "@ulams/sdk";
import type { Format } from "@ulams/ui/registry";

export const FORMAT_BY_KIND: Record<TopicKind, Format | undefined> = {
  richtext: "reading",
  video: "video",
  audio: "audio",
  image: "image",
  pdf: "pdf",
  oembed: "embed",
  h5p: "interactive",
  scorm: "scorm",
  cmi5: "tracked",
  quiz: "quiz",
  project: "project",
  unknown: undefined,
};

export interface ImageModel {
  src: string;
  alt: string;
  width: number;
  height: number;
}

export interface TopicModel {
  id: number;
  title: string;
  format?: Format;
  minutes: number;
  preview: boolean;
  href: string;
  status: "open" | "done" | "current" | "locked";
}

export interface LessonModel {
  id: number;
  title: string;
  kicker?: string;
  summary?: string;
  minutes: number;
  formats: Format[];
  topics: TopicModel[];
  href: string;
}

export interface PersonModel {
  name: string;
  role?: string;
  image?: ImageModel;
}

export interface PlanModel {
  name: string;
  label?: string;
  price?: string;
  period?: string;
  description?: string;
  items: string[];
  cta: { label: string; href: string };
  highlighted: boolean;
}

export interface EventModel {
  kind: "webinar" | "in-person";
  title: string;
  date?: string;
  place?: string;
  text?: string;
}

export interface CourseModel {
  id: number;
  title: string;
  subtitle?: string;
  summary?: string;
  description?: string;
  level?: string;
  language?: string;
  targetGroup?: string;
  href: string;
  learnHref: string;
  /** First free-preview topic, else the start of the course. */
  previewHref: string;
  image?: ImageModel;
  durationLabel?: string;
  lessonCount: number;
  topicCount: number;
  /** "6 lessons · 18 topics · ~9 h · 6 lessons" style summary from the API numbers. */
  lessonsLabel: string;
  minutes: number;
  lessons: LessonModel[];
  formats: Format[];
  tutors: PersonModel[];
  facts: Array<{ label: string; value: string; note?: string }>;
  price?: string;
  testimonials: Array<{ quote: string; author: string }>;
  faq: Array<{ q: string; a: string }>;
  /** String lists from the landing fields (e.g. for_parents) as `{title}` items. */
  lists: Record<string, Array<{ title: string }>>;
  /** `fields.badges` as Badges items. */
  badges: Array<{ name: string }>;
  /** Status line from `fields.landing.status_strip` (cohort courses). */
  statusStrip?: string;
  landing: Record<string, unknown>;
  fields: Record<string, unknown>;
}

export interface SiteModel {
  tenant: { slug: string; name: string; adminUrl: string };
  currency: string;
  course?: CourseModel;
  courses: Array<{ id: number; title: string; href: string; summary?: string; image?: ImageModel }>;
  plans: PlanModel[];
  events: EventModel[];
  webinar?: EventModel;
  inPerson?: EventModel;
}

const clean = (value: string | null | undefined): string | undefined => {
  const v = value?.trim();
  return v ? v : undefined;
};

/** Strips tags from API HTML (webinar descriptions) for plain-text props. */
export const plainText = (html: string | null | undefined): string | undefined =>
  clean(
    html
      ?.replace(/<[^>]*>/g, " ")
      .replace(/&nbsp;/g, " ")
      .replace(/&amp;/g, "&")
      .replace(/&quot;/g, '"')
      .replace(/&#39;/g, "'")
      .replace(/&lt;/g, "<")
      .replace(/&gt;/g, ">")
      .replace(/\s+/g, " ")
  );

export function formatMoney(minorUnits: number | null | undefined, currency = "EUR", locale = "en-GB"): string | undefined {
  if (minorUnits === null || minorUnits === undefined || Number.isNaN(minorUnits)) return undefined;
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

export const fullName = (user: Pick<UserSummary, "first_name" | "last_name">): string =>
  [user.first_name, user.last_name].filter(Boolean).join(" ");

/** "I. Origins" → {kicker: "I", title: "Origins"}; "Mission 1 · Lift-off" → {kicker: "Mission 1", title: "Lift-off"}. */
export function splitLessonTitle(title: string): { kicker?: string; title: string } {
  const numbered = title.match(/^\s*([IVXLC]+|\d+)\.\s+(.+)$/);
  if (numbered) return { kicker: numbered[1], title: numbered[2]!.trim() };
  const dotted = title.match(/^\s*(.+?)\s+[·:–—-]\s+(.+)$/);
  if (dotted && dotted[1]!.length <= 24) return { kicker: dotted[1]!.trim(), title: dotted[2]!.trim() };
  return { title: title.trim() };
}

export const topicHref = (courseId: number, topicId: number): string => `/learn/${courseId}/${topicId}`;

export function topicStatus(
  topic: Topic,
  progress: TopicProgress[] | null,
  currentId?: number,
  access = true
): TopicModel["status"] {
  if (topic.id === currentId) return "current";
  if (!access && !topic.preview) return "locked";
  const p = progress?.find((x) => x.topic_id === topic.id);
  return p?.status === 1 ? "done" : "open";
}

export function lessonModels(
  course: Course,
  options: { progress?: TopicProgress[] | null; currentTopicId?: number; access?: boolean } = {}
): LessonModel[] {
  const sortTopics = (topics: Topic[]) => [...topics].sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
  const lessons: Lesson[] = [...(course.lessons ?? [])].sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
  return lessons.map((lesson) => {
    const topics = sortTopics(lesson.topics ?? []);
    const formats: Format[] = [];
    for (const topic of topics) {
      const format = FORMAT_BY_KIND[topicKind(topic.topicable_type)];
      if (format && !formats.includes(format)) formats.push(format);
    }
    const { kicker, title } = splitLessonTitle(lesson.title);
    const topicModels = topics.map<TopicModel>((topic) => ({
      id: topic.id,
      title: topic.title,
      format: FORMAT_BY_KIND[topicKind(topic.topicable_type)],
      minutes: durationToMinutes(topic.duration),
      preview: Boolean(topic.preview),
      href: topicHref(course.id, topic.id),
      status: topicStatus(topic, options.progress ?? null, options.currentTopicId, options.access ?? true),
    }));
    const minutes = topicModels.reduce((sum, t) => sum + t.minutes, 0) || durationToMinutes(lesson.duration);
    return {
      id: lesson.id,
      title,
      kicker,
      summary: clean(lesson.summary),
      minutes,
      formats,
      topics: topicModels,
      href: topicModels[0]?.href ?? `/courses/${course.id}`,
    };
  });
}

export function imageFromUrl(url: string | null | undefined, alt: string, width = 1600, height = 900): ImageModel | undefined {
  return url ? { src: url, alt, width, height } : undefined;
}

function landingFields(course: Course): Record<string, unknown> {
  const fields = (course.fields ?? {}) as Record<string, unknown>;
  const landing = fields.landing;
  return landing && typeof landing === "object" ? (landing as Record<string, unknown>) : {};
}

const pairs = <T extends Record<string, string>>(value: unknown, keys: Array<keyof T>): T[] =>
  Array.isArray(value)
    ? value.filter((v): v is T => !!v && typeof v === "object" && keys.every((k) => typeof (v as Record<string, unknown>)[k as string] === "string"))
    : [];

const strings = (value: unknown): string[] =>
  Array.isArray(value) ? value.filter((v): v is string => typeof v === "string" && v.trim() !== "") : [];

const sentence = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

function stringLists(landing: Record<string, unknown>): Record<string, Array<{ title: string }>> {
  const out: Record<string, Array<{ title: string }>> = {};
  for (const [key, value] of Object.entries(landing)) {
    const list = strings(value);
    if (list.length) out[key] = list.map((title) => ({ title: sentence(title) }));
  }
  return out;
}

export function courseModel(course: Course, currency = "EUR", tutors: UserSummary[] = []): CourseModel {
  const lessons = lessonModels(course);
  const topics = flattenTopics(course);
  const minutes = lessons.reduce((sum, l) => sum + l.minutes, 0);
  const formats: Format[] = [];
  for (const lesson of lessons) for (const f of lesson.formats) if (!formats.includes(f)) formats.push(f);
  const preview = topics.find((t) => t.preview);
  const landing = landingFields(course);
  const people = (course.authors?.length ? course.authors : tutors).map<PersonModel>((a) => ({
    name: fullName(a),
    image: a.url_avatar ? { src: a.url_avatar, alt: fullName(a), width: 256, height: 256 } : undefined,
  }));
  const price = formatMoney(course.product?.price, currency);
  const facts: CourseModel["facts"] = [];
  if (clean(course.level)) facts.push({ label: "Level", value: course.level!.trim() });
  if (course.hours_to_complete) facts.push({ label: "Length", value: `${course.hours_to_complete} hours`, note: "self-paced" });
  else if (clean(course.duration)) facts.push({ label: "Length", value: course.duration!.trim() });
  facts.push({ label: "Topics", value: String(topics.length), note: `in ${lessons.length} lessons` });

  return {
    id: course.id,
    title: course.title,
    subtitle: clean(course.subtitle),
    summary: clean(course.summary),
    description: clean(course.description),
    level: clean(course.level),
    language: clean(course.language),
    targetGroup: clean(course.target_group),
    href: `/courses/${course.id}`,
    learnHref: `/learn/${course.id}`,
    previewHref: preview ? topicHref(course.id, preview.id) : `/learn/${course.id}`,
    image: imageFromUrl(course.image_url ?? course.poster_url, course.title),
    durationLabel: clean(course.duration),
    lessonCount: lessons.length,
    topicCount: topics.length,
    lessonsLabel: [`${lessons.length} lessons`, `${topics.length} topics`, minutes ? `about ${Math.round(minutes / 60)} h of material` : ""]
      .filter(Boolean)
      .join(" · "),
    minutes,
    lessons,
    formats,
    tutors: people,
    facts,
    price,
    testimonials: pairs<{ quote: string; author: string }>(landing.testimonials, ["quote", "author"]),
    faq: pairs<{ q: string; a: string }>(landing.faq, ["q", "a"]),
    lists: stringLists(landing),
    badges: strings((course.fields ?? {}).badges).map((name) => ({ name })),
    statusStrip: typeof landing.status_strip === "string" ? landing.status_strip : undefined,
    landing,
    fields: (course.fields ?? {}) as Record<string, unknown>,
  };
}

const periodLabel = (period: string | null | undefined): string =>
  ({ daily: "per day", weekly: "per week", monthly: "per month", yearly: "per year" })[period ?? ""] ?? "subscription";

export function planModels(products: Product[], currency: string, courseHref: string): PlanModel[] {
  return products.map((product, index) => {
    const included = (product.productables ?? [])
      .map((p) => (p as { name?: string }).name)
      .filter((n): n is string => typeof n === "string" && n.trim() !== "");
    return {
      name: product.name,
      label: product.type === "bundle" ? "Package" : product.type === "subscription" ? "Subscription" : "Course",
      price: formatMoney(product.price, currency),
      period: product.type === "subscription" ? periodLabel(product.subscription_period) : "one-off",
      description: clean(product.description),
      items: included,
      cta: { label: "Start with the demo", href: courseHref },
      highlighted: product.type === "bundle" || (products.length > 1 && index === 1 && product.type !== "single"),
    };
  });
}

export function eventModels(webinars: Webinar[], events: StationaryEvent[], now = Date.now()): EventModel[] {
  const upcoming = (date?: string | null) => !date || Date.parse(date.replace(" ", "T")) >= now - 86_400_000;
  const w = webinars
    .filter((x) => !x.is_ended && upcoming(x.active_to ?? x.active_from))
    .map<EventModel>((x) => ({
      kind: "webinar",
      title: x.name,
      date: x.active_from ?? undefined,
      text: clean(x.short_desc) ?? plainText(x.description),
    }));
  const e = events
    .filter((x) => !x.is_ended && upcoming(x.finished_at ?? x.started_at))
    .map<EventModel>((x) => ({
      kind: "in-person",
      title: x.name,
      // naive wall-clock time from the API: kept as UTC and shown without a zone
      date: x.started_at ? `${x.started_at.replace(" ", "T")}Z` : undefined,
      place: clean(x.place),
      text: clean(x.short_desc) ?? plainText(x.description),
    }));
  return [...w, ...e].sort((a, b) => (a.date ?? "").localeCompare(b.date ?? ""));
}

export interface RawSiteData {
  settings: PublicSettings | null;
  courses: Course[];
  course: Course | null;
  tutors: UserSummary[];
  webinars: Webinar[];
  events: StationaryEvent[];
  products: Product[];
}

export function siteModel(raw: RawSiteData, tenant: { slug: string; adminUrl: string }): SiteModel {
  const currency = (raw.settings?.currencies?.default as string | undefined) || "EUR";
  const course = raw.course ? courseModel(raw.course, currency, raw.tutors) : undefined;
  const events = eventModels(raw.webinars, raw.events);
  return {
    tenant: {
      slug: tenant.slug,
      name: raw.settings?.global?.companyName || course?.title || tenant.slug,
      adminUrl: tenant.adminUrl,
    },
    currency,
    course,
    courses: raw.courses.map((c) => ({
      id: c.id,
      title: c.title,
      href: `/courses/${c.id}`,
      summary: clean(c.summary),
      image: imageFromUrl(c.image_url, c.title),
    })),
    plans: planModels(raw.products, currency, course?.previewHref ?? "/"),
    events,
    webinar: events.find((e) => e.kind === "webinar"),
    inPerson: events.find((e) => e.kind === "in-person"),
  };
}
