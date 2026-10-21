/**
 * Documents for the course page and the lesson player, built from API data. They use the
 * same catalogue and renderer as the landing documents, so the Course Builder can later
 * produce them the same way.
 */
import { durationToMinutes, topicKind, type Course, type Lesson, type Presentation, type Tenant, type Topic } from "@ulams/sdk";
import type { UiLocale } from "@ulams/ui/i18n";
import { fill, plural, playerStrings } from "@ulams/ui/player-strings";
import type { UiNode } from "@ulams/ui/render-core";
import { LEARNER_LAYOUT_COMPONENTS, registry, type ComponentName, type ThemeName } from "@ulams/ui/registry";
import { stripLeadingTitle } from "@ulams/ui/markdown";
import { validate } from "@ulams/ui/schema";
import { interactiveNode, type InteractiveResult } from "./interactive.ts";
import { codeExerciseNode, type CodeExerciseResult } from "./code-exercise.ts";
import { DEFAULT_PRESENTATION, proseNode, withInitialHints } from "./presentation.ts";
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

/** Level, length and topic count of the course header, in the language (the English facts come from the model). */
function courseFacts(course: CourseModel, locale: UiLocale): CourseModel["facts"] {
  if (locale === "en") return course.facts;
  const t = playerStrings(locale);
  const facts: CourseModel["facts"] = [];
  if (course.level) facts.push({ label: t.factLevel, value: course.level });
  if (course.hours) facts.push({ label: t.factLength, value: plural(locale, t.hoursValue, course.hours), note: t.selfPaced });
  else if (course.durationLabel) facts.push({ label: t.factLength, value: course.durationLabel });
  facts.push({ label: t.factTopics, value: String(course.topicCount), note: plural(locale, t.inLessons, course.lessonCount) });
  return facts;
}

export function courseHeaderDoc(theme: ThemeName, course: CourseModel, progress: number | undefined, resumeHref: string, progressNote?: string, locale: UiLocale = "en"): UiNode {
  const t = playerStrings(locale);
  return {
    component: "CourseHeader",
    props: {
      variant: HERO_VARIANT[theme],
      eyebrow: course.level ? `${course.level} · ${locale === "en" ? `${course.lessonCount} ${LESSON_NOUN[theme].toLowerCase()}s` : plural(locale, t.eyebrowLessons, course.lessonCount)}` : undefined,
      title: course.title,
      subtitle: course.subtitle,
      summary: course.summary,
      facts: [
        ...courseFacts(course, locale),
        ...(course.language ? [{ label: t.factLanguage, value: course.language.toUpperCase() }] : []),
      ].slice(0, 6),
      image: course.image,
      tutors: course.tutors.slice(0, 4),
      primaryCta: { label: progress ? t.continueLearning : t.startLearning, href: resumeHref },
      secondaryCta: course.previewHref !== course.learnHref ? { label: t.freePreview, href: course.previewHref } : undefined,
      progress,
      progressNote: progressNote || undefined,
    },
  };
}

export function syllabusDoc(theme: ThemeName, course: CourseModel, locale: UiLocale = "en"): UiNode {
  const t = playerStrings(locale);
  const titles = t.syllabusTitle as Record<string, string>;
  return {
    component: "Syllabus",
    props: {
      variant: SYLLABUS_VARIANT[theme],
      eyebrow: t.syllabusEyebrow,
      title: titles[theme] ?? t.syllabusTitleDefault,
      lessons: course.lessons,
      totalLabel: locale === "en" ? course.lessonsLabel : [plural(locale, t.lessonsCount, course.lessonCount), plural(locale, t.topicsCount, course.topicCount), course.minutes ? fill(t.materialHours, { n: Math.round(course.minutes / 60) }) : ""].filter(Boolean).join(" · "),
    },
  };
}

export function descriptionDoc(theme: ThemeName, course: CourseModel): UiNode | null {
  return course.description ? { component: "Prose", props: { markdown: course.description, dropCap: theme === "coffee" } } : null;
}

export function purchaseDoc(course: CourseModel, site: SiteModel, resumeHref: string, locale: UiLocale = "en"): UiNode {
  const plan = site.plans[0];
  const t = playerStrings(locale);
  const formats = course.formats.slice(0, 5).map((f) => (t.formats as Record<string, string>)[f] ?? f);
  return {
    component: "PurchaseCard",
    props: {
      title: t.demoAccess,
      price: course.price ?? plan?.price,
      priceNote: course.price ? t.listPrice : plan?.period,
      items: [
        locale === "en" ? `${course.lessonCount} lessons, ${course.topicCount} topics` : `${plural(locale, t.lessonsCount, course.lessonCount)}, ${plural(locale, t.topicsCount, course.topicCount)}`,
        ...(course.formats.length ? [fill(t.formatsList, { list: locale === "en" ? course.formats.slice(0, 5).join(", ") : formats.join(", ") })] : []),
        t.progressSaved,
        t.everyLessonOpen,
      ],
      cta: { label: t.openCourse, href: resumeHref },
      note: t.paymentsNote,
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
  /** For code exercise topics: the launch (or the author preview) result (see codeExerciseLaunch). */
  codeExercise?: CodeExerciseResult | null;
  /** For external-tool topics: the LTI launch (see ltiLaunch). */
  lti?: { url: string; presentation: string; tool: string } | { error: string } | null;
  /** Author preview: nothing that records progress runs (quizzes, tracked launches, H5P xAPI). */
  preview?: boolean;
  /** The learner's lesson view: parts for a reading, hints shown at the start of a practice. */
  presentation?: Presentation;
  locale?: UiLocale;
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
export function topicDoc({ tenant, theme, course, topic, access, nextHref, packageAvailable = true, contentOriginSrc = null, cmi5Src = null, liascript = null, interactive = null, codeExercise = null, lti = null, preview = false, presentation = DEFAULT_PRESENTATION, locale = "en" }: TopicDocInput): UiNode {
  const kind = topicKind(topic.topicable_type);
  const s = playerStrings(locale);
  const t = (topic.topicable ?? {}) as Record<string, unknown>;
  const children: UiNode[] = [];
  const description = str(topic.description);

  if (!topic.topicable) {
    children.push({
      component: "ActivityCard",
      props: {
        kind: "locked",
        title: access ? s.topicNotLoadedTitle : s.topicLockedTitle,
        text: access ? s.topicNotLoadedText : s.topicLockedText,
        cta: { label: s.backToCourse, href: `/courses/${course.id}` },
      },
    });
    return { component: "Stack", props: { gap: "lg" }, children };
  }

  if (preview && (kind === "quiz" || kind === "liascript" || kind === "lti")) {
    children.push({
      component: "Callout",
      props: {
        tone: "key",
        title: kind === "quiz" ? s.previewQuizTitle : s.previewOtherTitle,
        text: kind === "quiz" ? s.previewQuizText : s.previewOtherText,
      },
    });
    if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
    return { component: "Stack", props: { gap: "lg" }, children };
  }

  switch (kind) {
    case "richtext": {
      const md = stripLeadingTitle(String(t.value ?? ""), topic.title);
      children.push(proseNode(md, { dropCap: theme === "coffee", size: theme === "nightsky" ? "lg" : "md" }, presentation, locale));
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
                title: fill(s.scormTitle, { title: topic.title }),
                text: s.scormText,
                cta: { label: s.scormTry, href: src },
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
                props: { kind: "tracked", title: topic.title, text: fill(s.ltiOpens, { tool: lti.tool || s.ltiToolFallback }), cta: { label: s.ltiOpen, href: lti.url } },
              }
            : { component: "Callout", props: { tone: "warning", title: topic.title, text: lti && "error" in lti ? lti.error : s.ltiNeedsEnrolment } }
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
              props: { tone: "warning", title: topic.title, text: liascript && "error" in liascript ? liascript.error : s.liaNeedsEnrolment },
            }
      );
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    case "interactive": {
      children.push(
        !interactive && preview
          ? { component: "Callout", props: { tone: "key", title: s.previewOtherTitle, text: s.previewInteractiveText } }
          : interactiveNode(interactive, { title: topic.title, course, topicId: topic.id, preview })
      );
      // the topic's own text still shows when the package cannot be played
      const own = str(t.text);
      if (own && (!interactive || "error" in interactive)) children.push({ component: "Prose", props: { markdown: own } });
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    }
    case "code_exercise": {
      children.push(
        !codeExercise && preview
          ? { component: "Callout", props: { tone: "key", title: "Not run in preview", text: "Learners edit and run this exercise here, and each run is stored as a practice attempt. This preview does not start it, so nothing is recorded." } }
          : codeExerciseNode(codeExercise, { title: topic.title, course, topicId: topic.id, preview, locale })
      );
      // the topic's own text still shows when the exercise cannot be opened
      const own = str(t.text);
      if (own && (!codeExercise || "error" in codeExercise)) children.push({ component: "Prose", props: { markdown: own } });
      if (description) children.push({ component: "Prose", props: { markdown: description, size: "sm" } });
      break;
    }
    case "layout": {
      // catalogue components from the stored document (ADR 0052); an invalid document shows the fallback
      const nodes = layoutNodes(t);
      if (nodes) children.push(...withInitialHints(nodes, presentation.hints));
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
          title: s.projectTitle,
          text: s.projectText,
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
                text: str(topic.introduction) ?? s.cmi5Text,
                steps: description ? undefined : [s.cmi5Step1, s.cmi5Step2, s.cmi5Step3],
              },
            }
      );
      if (description) children.push({ component: "Prose", props: { markdown: description } });
      break;
    default:
      children.push({ component: "Callout", props: { tone: "warning", title: s.unsupportedTopic, text: topic.topicable_type } });
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
    case "code_exercise":
      // a passing run (or any run, by the exercise's rule) completes the topic: `ulams:complete` from <ulams-code-exercise>
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
