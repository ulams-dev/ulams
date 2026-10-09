/**
 * Documents for the course page and the lesson player, built from API data. They use the
 * same catalogue and renderer as the landing documents, so the Course Builder can later
 * produce them the same way.
 */
import { durationToMinutes, topicKind, type Course, type Lesson, type Tenant, type Topic } from "@ulams/sdk";
import type { UiNode } from "@ulams/ui/render-core";
import { LEARNER_LAYOUT_COMPONENTS, registry, type ComponentName, type ThemeName } from "@ulams/ui/registry";
import { stripLeadingTitle } from "@ulams/ui/markdown";
import { validate } from "@ulams/ui/schema";
import { interactiveNode, type InteractiveResult } from "./interactive.ts";
import { FORMAT_BY_KIND, type CourseModel, type SiteModel } from "./view-model.ts";

export const SYLLABUS_VARIANT: Record<ThemeName, "folio" | "timeline" | "missions" | "orbits" | "atlas"> = {
  coffee: "folio",
  oncall: "timeline",
  nightsky: "missions",
  gravity: "orbits",
  poland: "atlas",
  ulam: "timeline",
  platform: "folio",
};

export const HERO_VARIANT: Record<ThemeName, "editorial" | "console" | "adventure" | "product"> = {
  coffee: "editorial",
  oncall: "console",
  nightsky: "adventure",
  gravity: "editorial",
  poland: "editorial",
  ulam: "editorial",
  platform: "product",
};

const LESSON_NOUN: Record<ThemeName, string> = { coffee: "Chapter", oncall: "Module", nightsky: "Mission", gravity: "Module", poland: "Chapter", ulam: "Notebook", platform: "Lesson" };

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
      title: { nightsky: "Your mission map", gravity: "Your flight plan", poland: "Contents", ulam: "The notebooks" }[theme as string] ?? "What you will do",
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
  /** For cmi5 topics: the AU launch URL on the tenant content origin (see cmi5Launch). */
  cmi5Src?: string | null;
  /** For LiaScript topics: the launch result (see liascriptLaunch). */
  liascript?: { url: string; sections?: number } | { error: string } | null;
  /** For Interactive topics: the launch (or the author preview) result (see interactiveLaunch). */
  interactive?: InteractiveResult | null;
  /** For external-tool topics: the LTI launch (see ltiLaunch). */
  lti?: { url: string; presentation: string; tool: string } | { error: string } | null;
  /** Author preview: nothing that records progress runs (quizzes, tracked launches, H5P xAPI). */
  preview?: boolean;
}

const str = (v: unknown): string | undefined => (typeof v === "string" && v.trim() !== "" ? v : undefined);
const num = (v: unknown): number | undefined => (typeof v === "number" && Number.isFinite(v) ? v : typeof v === "string" && v !== "" && !Number.isNaN(Number(v)) ? Number(v) : undefined);

const LAYOUT_COMPONENTS: readonly string[] = LEARNER_LAYOUT_COMPONENTS;

/**
 * The document of a Layout topic when it is safe to render: a non-empty list of nodes that use only the
 * approved layout components, with props that match the catalogue. The API checks the same rules when the
 * topic is saved; this check protects the page from content that predates a catalogue change. `null`
 * means "show the Markdown fallback instead".
 */
export function layoutNodes(topicable: unknown): UiNode[] | null {
  const document = (topicable as { document?: unknown } | null)?.document;
  if (!Array.isArray(document) || document.length === 0) return null;
  const nodes: UiNode[] = [];
  for (const raw of document) {
    const node = raw as { component?: unknown; props?: unknown; id?: unknown } | null;
    if (!node || typeof node !== "object" || typeof node.component !== "string" || !LAYOUT_COMPONENTS.includes(node.component)) return null;
    const props = node.props === undefined ? {} : node.props;
    if (!props || typeof props !== "object" || Array.isArray(props)) return null;
    if (validate(registry[node.component as ComponentName].props, props).issues.length > 0) return null;
    nodes.push({ component: node.component, props: props as Record<string, unknown>, ...(typeof node.id === "string" ? { id: node.id } : {}) });
  }
  return nodes;
}

/** Body of the lesson player for one topic. */
export function topicDoc({ tenant, theme, course, topic, access, nextHref, packageAvailable = true, contentOriginSrc = null, cmi5Src = null, liascript = null, interactive = null, lti = null, preview = false }: TopicDocInput): UiNode {
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

  if (preview && (kind === "quiz" || kind === "liascript" || kind === "lti")) {
    children.push({
      component: "Callout",
      props: {
        tone: "key",
        title: kind === "quiz" ? "Quiz: not run in preview" : "Not launched in preview",
        text:
          kind === "quiz"
            ? "Learners answer this quiz and see their score here. The preview does not start attempts, so nothing is saved."
            : "Learners open this activity here and it reports their progress. The preview does not launch it, so nothing is recorded.",
      },
    });
    if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
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
        props: { apiUrl: tenant.apiUrl, contentId: num(t.value) ?? 0, title: topic.title, ...(preview ? {} : { topicId: topic.id, courseId: course.id }) },
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
    case "interactive": {
      children.push(
        !interactive && preview
          ? { component: "Callout", props: { tone: "key", title: "Not launched in preview", text: "Learners open this interactive here and it reports their progress. This preview does not launch it, so nothing is recorded." } }
          : interactiveNode(interactive, { title: topic.title, course, topicId: topic.id, preview })
      );
      // the topic's own text still shows when the package cannot be played
      const own = str(t.text);
      if (own && (!interactive || "error" in interactive)) children.push({ component: "Prose", props: { markdown: own } });
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    }
    case "layout": {
      // catalogue components from the stored document (ADR 0052); an invalid document shows the fallback
      const nodes = layoutNodes(t);
      if (nodes) children.push(...nodes);
      else children.push({ component: "Prose", props: { markdown: str(t.markdown_fallback) ?? "" } });
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    }
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
      children.push(
        cmi5Src
          ? { component: "PackageFrame", props: { src: cmi5Src, title: topic.title, height: 640, isolated: true } }
          : {
              component: "ActivityCard",
              props: {
                kind: "tracked",
                title: topic.title,
                text: str(topic.introduction) ?? "A tracked activity that reports your progress back. It opens here once you are enrolled and the tenant has a content origin.",
                steps: description ? undefined : ["Open the activity", "Complete it", "Your progress is saved"],
              },
            }
      );
      if (description) children.push({ component: "Prose", props: { markdown: description } });
      break;
    default:
      children.push({ component: "Callout", props: { tone: "warning", title: "Unsupported topic type", text: topic.topicable_type } });
  }
  return { component: "Stack", props: { gap: "lg" }, children };
}

/** How a topic gets marked complete in the player. */
export function completionMode(topic: Topic): "view" | "manual" | "media" | "h5p" | "quiz" | "external" {
  switch (topicKind(topic.topicable_type)) {
    case "video":
    case "audio":
      return "media";
    case "h5p":
      return "h5p";
    case "interactive":
      // the package completes the topic through the bridge: `ulams:complete` from <ulams-interactive>
      return "external";
    case "layout":
      // viewed, except that a practice activity completes the topic with its first checked attempt
      return layoutNodes(topic.topicable)?.some((node) => node.component === "PracticeActivity") ? "external" : "view";
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
