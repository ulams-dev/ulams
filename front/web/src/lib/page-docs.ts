/**
 * Documents for the course page and the lesson player, built from API data. They use the
 * same catalogue and renderer as the landing documents, so the Course Builder can later
 * produce them the same way.
 */
import { durationToMinutes, topicKind, type Course, type Lesson, type Tenant, type Topic } from "@ulams/sdk";
import type { UiNode } from "@ulams/ui/render-core";
import type { ThemeName } from "@ulams/ui/registry";
import { stripLeadingTitle } from "@ulams/ui/markdown";
import { FORMAT_BY_KIND, type CourseModel, type SiteModel } from "./view-model.ts";

export const SYLLABUS_VARIANT: Record<ThemeName, "folio" | "timeline" | "missions"> = {
  coffee: "folio",
  oncall: "timeline",
  nightsky: "missions",
  platform: "folio",
};

export const HERO_VARIANT: Record<ThemeName, "editorial" | "console" | "adventure" | "product"> = {
  coffee: "editorial",
  oncall: "console",
  nightsky: "adventure",
  platform: "product",
};

const LESSON_NOUN: Record<ThemeName, string> = { coffee: "Chapter", oncall: "Module", nightsky: "Mission", platform: "Lesson" };

export function courseHeaderDoc(theme: ThemeName, course: CourseModel, progress: number | undefined, resumeHref: string): UiNode {
  return {
    component: "CourseHeader",
    props: {
      variant: HERO_VARIANT[theme],
      eyebrow: course.level ? `${course.level} · ${course.lessonCount} ${LESSON_NOUN[theme].toLowerCase()}s` : undefined,
      title: course.title,
      subtitle: course.subtitle,
      summary: course.summary,
      facts: [
        ...course.facts,
        ...(course.language ? [{ label: "Language", value: course.language.toUpperCase() }] : []),
      ].slice(0, 6),
      image: course.image,
      tutors: course.tutors.slice(0, 4),
      primaryCta: { label: progress ? "Continue learning" : "Start learning", href: resumeHref },
      secondaryCta: course.previewHref !== course.learnHref ? { label: "Free preview", href: course.previewHref } : undefined,
      progress,
    },
  };
}

export function syllabusDoc(theme: ThemeName, course: CourseModel): UiNode {
  return {
    component: "Syllabus",
    props: {
      variant: SYLLABUS_VARIANT[theme],
      eyebrow: "Program",
      title: theme === "nightsky" ? "Your mission map" : "What you will do",
      lessons: course.lessons,
      totalLabel: course.lessonsLabel,
    },
  };
}

export function descriptionDoc(theme: ThemeName, course: CourseModel): UiNode | null {
  return course.description ? { component: "Prose", props: { markdown: course.description, dropCap: theme === "coffee" } } : null;
}

export function purchaseDoc(course: CourseModel, site: SiteModel, resumeHref: string): UiNode {
  const plan = site.plans[0];
  return {
    component: "PurchaseCard",
    props: {
      title: "Demo access",
      price: course.price ?? plan?.price,
      priceNote: course.price ? "list price" : plan?.period,
      items: [
        `${course.lessonCount} lessons, ${course.topicCount} topics`,
        ...(course.formats.length ? [`Formats: ${course.formats.slice(0, 5).join(", ")}`] : []),
        "Progress saved as you go",
        "Every lesson open in this demo",
      ],
      cta: { label: "Open the course", href: resumeHref },
      note: "Payments run through the shop service in production; this demo opens the course directly.",
    },
  };
}

/** Topic duration label, e.g. "12 min". */
export const topicMinutes = (topic: Topic): number => durationToMinutes(topic.duration);

export interface TopicDocInput {
  tenant: Tenant;
  theme: ThemeName;
  course: Course;
  lesson: Lesson | undefined;
  topic: Topic;
  access: boolean;
  nextHref: string;
  /** For SCORM topics: whether the package files load (see scormAvailable). */
  packageAvailable?: boolean;
  /** For SCORM topics: player on the tenant content origin (see scormLaunch), preferred when set. */
  contentOriginSrc?: string | null;
  /** For LiaScript topics: the launch result (see liascriptLaunch). */
  liascript?: { url: string; sections?: number } | { error: string } | null;
  /** For external-tool topics: the LTI launch (see ltiLaunch). */
  lti?: { url: string; presentation: string; tool: string } | { error: string } | null;
}

const str = (v: unknown): string | undefined => (typeof v === "string" && v.trim() !== "" ? v : undefined);
const num = (v: unknown): number | undefined => (typeof v === "number" && Number.isFinite(v) ? v : typeof v === "string" && v !== "" && !Number.isNaN(Number(v)) ? Number(v) : undefined);

/** Body of the lesson player for one topic. */
export function topicDoc({ tenant, theme, course, topic, access, nextHref, packageAvailable = true, contentOriginSrc = null, liascript = null, lti = null }: TopicDocInput): UiNode {
  const kind = topicKind(topic.topicable_type);
  const t = (topic.topicable ?? {}) as Record<string, unknown>;
  const children: UiNode[] = [];
  const description = str(topic.description);

  if (!topic.topicable) {
    children.push({
      component: "ActivityCard",
      props: {
        kind: "locked",
        title: access ? "This topic could not be loaded" : "Locked in this demo account",
        text: access
          ? "The API returned the topic without its content. Try again in a moment."
          : "The demo student has no access to this course yet, so only the free preview topics open. Turn on demo mode for the tenant (or grant the student access in the admin) to open every topic.",
        cta: { label: "Back to the course", href: `/courses/${course.id}` },
      },
    });
    return { component: "Stack", props: { gap: "lg" }, children };
  }

  switch (kind) {
    case "richtext": {
      const md = stripLeadingTitle(String(t.value ?? ""), topic.title);
      children.push({ component: "Prose", props: { markdown: md, dropCap: theme === "coffee", size: theme === "nightsky" ? "lg" : "md" } });
      break;
    }
    case "video": {
      const chapters = Array.isArray(topic.json?.chapters) ? topic.json!.chapters!.filter((c) => typeof c.time === "number" && typeof c.title === "string") : [];
      children.push({
        component: "VideoPlayer",
        props: {
          src: str(t.url) ?? "",
          poster: str(t.poster_url),
          width: num(t.width) ?? 1280,
          height: num(t.height) ?? 720,
          title: topic.title,
          chapters,
        },
      });
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    }
    case "audio":
      children.push({ component: "AudioPlayer", props: { src: str(t.url) ?? "", title: topic.title, seconds: num(t.length) ? Math.round(num(t.length)! / 1000) : undefined } });
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    case "image":
      children.push({
        component: "Figure",
        props: {
          image: { src: str(t.url) ?? "", alt: str(topic.summary) ?? topic.title, width: num(t.width) ?? 1600, height: num(t.height) ?? 900 },
          caption: str(topic.summary),
        },
      });
      if (description) children.push({ component: "Prose", props: { markdown: description } });
      break;
    case "pdf":
      children.push({ component: "PdfViewer", props: { src: str(t.url) ?? "", title: topic.title } });
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    case "oembed":
      children.push({ component: "Embed", props: { url: str(t.value) ?? "", title: topic.title } });
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    case "h5p":
      children.push({
        component: "H5PFrame",
        props: { apiUrl: tenant.apiUrl, contentId: num(t.value) ?? 0, title: topic.title, topicId: topic.id, courseId: course.id },
      });
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    case "scorm": {
      const src = `${tenant.apiUrl}/api/scorm/play/${encodeURIComponent(str(t.uuid) ?? "")}`;
      children.push(
        contentOriginSrc
          ? { component: "PackageFrame", props: { src: contentOriginSrc, title: topic.title, height: 640, isolated: true } }
          : packageAvailable
          ? { component: "PackageFrame", props: { src, title: topic.title, height: 640 } }
          : {
              component: "ActivityCard",
              props: {
                kind: "tracked",
                title: `${topic.title} (SCORM package)`,
                text: "The package is uploaded, but this API does not serve its files yet (tenant storage is not published under /storage). The player opens here as soon as the files load.",
                cta: { label: "Try the player in a new tab", href: src },
              },
            }
      );
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    }
    case "lti":
      if (lti && "url" in lti && lti.presentation !== "window") {
        children.push({ component: "PackageFrame", props: { src: lti.url, title: topic.title, height: 720, isolated: true } });
      } else {
        children.push(
          lti && "url" in lti
            ? {
                component: "ActivityCard",
                props: { kind: "tracked", title: topic.title, text: `Opens in ${lti.tool || "the tool"} in a new window; your result comes back here.`, cta: { label: "Open the activity", href: lti.url } },
              }
            : { component: "Callout", props: { tone: "warning", title: topic.title, text: lti && "error" in lti ? lti.error : "This activity opens once you are enrolled." } }
        );
      }
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    case "liascript":
      children.push(
        liascript && "url" in liascript
          ? { component: "LiaScriptLesson", props: { src: liascript.url, title: topic.title, ...(liascript.sections ? { sections: liascript.sections } : {}) } }
          : {
              component: "Callout",
              props: { tone: "warning", title: topic.title, text: liascript && "error" in liascript ? liascript.error : "This course opens once you are enrolled." },
            }
      );
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    case "quiz":
      children.push({
        component: "QuizRunner",
        props: {
          id: "quiz",
          quizId: num(t.id) ?? 0,
          title: topic.title,
          intro: str(t.value),
          maxAttempts: num(t.max_attempts) ?? 0,
          minutes: num(t.max_execution_time) ?? 0,
          passScore: num(t.min_pass_score) ?? 0,
          courseId: course.id,
          topicId: topic.id,
          finishHref: nextHref,
        },
      });
      break;
    case "project":
      children.push({ component: "Prose", props: { markdown: stripLeadingTitle(String(t.value ?? ""), topic.title) } });
      children.push({
        component: "ActivityCard",
        props: {
          kind: "project",
          title: "Hand in your work",
          text: "In the full app you upload your files here and your tutor replies in a feedback thread. File upload is not part of this demo yet.",
        },
      });
      break;
    case "cmi5":
      children.push({
        component: "ActivityCard",
        props: {
          kind: "tracked",
          title: topic.title,
          text: str(topic.introduction) ?? "A tracked activity that opens outside this page and reports your progress back.",
          steps: description ? undefined : ["Open the activity", "Complete it in the new window", "Come back: your progress is saved"],
        },
      });
      if (description) children.push({ component: "Prose", props: { markdown: description } });
      break;
    default:
      children.push({ component: "Callout", props: { tone: "warning", title: "Unsupported topic type", text: topic.topicable_type } });
  }
  return { component: "Stack", props: { gap: "lg" }, children };
}

/** How a topic gets marked complete in the player. */
export function completionMode(topic: Topic): "view" | "manual" | "media" | "h5p" | "quiz" {
  switch (topicKind(topic.topicable_type)) {
    case "video":
    case "audio":
      return "media";
    case "h5p":
      return "h5p";
    case "quiz":
      return "quiz";
    case "scorm":
    case "liascript":
    case "lti":
    case "cmi5":
    case "project":
      return "manual";
    default:
      return "view";
  }
}

export const formatOf = (topic: Topic) => FORMAT_BY_KIND[topicKind(topic.topicable_type)];
