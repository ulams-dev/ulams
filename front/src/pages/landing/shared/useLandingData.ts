import { useContext, useEffect, useMemo, useState } from "react";
import { UlamsContext } from "@ulams/sdk/react/context";
import { API } from "@ulams/sdk";
import { course as fetchCourses, getCourse, tutors as fetchTutors } from "@ulams/sdk/services/courses";
import { webinars as fetchWebinars } from "@ulams/sdk/services/webinars";
import { stationaryEvents as fetchStationaryEvents } from "@ulams/sdk/services/stationary_events";
import { products as fetchProducts } from "@ulams/sdk/services/products";
import { consultations as fetchConsultations } from "@ulams/sdk/services/consultations";
import routeRoutes from "@/components/Routes/routes";
import { durationToMinutes, formatMoney } from "./format";
import { FormatKey, formatFromTopicType } from "./formats";

export interface LandingLesson {
  id: number;
  title: string;
  summary?: string | null;
  formats: FormatKey[];
  topicCount: number;
  minutes: number;
}

export interface LandingData {
  loading: boolean;
  companyName?: string;
  currency: string;
  /** The tenant's demo course (matched by title hint), else the first course. */
  course?: API.Course;
  courses: API.Course[];
  lessons: LandingLesson[];
  tutors: API.UserItem[];
  webinars: API.Webinar[];
  events: API.StationaryEvent[];
  products: API.Product[];
  consultations: API.Consultation[];
  /** Course page, or the catalogue when there is no course yet. */
  courseHref: string;
  /** Course price formatted with the tenant currency, when the API has one. */
  coursePrice: string | null;
}

type Listish<T> = { success?: boolean; data?: T[] } | undefined;

const listOf = <T>(response: unknown): T[] => {
  const data = (response as Listish<T>)?.data;
  return Array.isArray(data) ? data : [];
};

/** Settles every request independently: a failing endpoint only removes its own section's data. */
const settle = <T>(promise: Promise<unknown>): Promise<T[]> =>
  promise.then((r) => listOf<T>(r)).catch(() => []);

const lessonsFromCourse = (course?: API.Course): LandingLesson[] =>
  (course?.lessons ?? []).map((lesson) => {
    const topics = lesson.topics ?? [];
    const formats = Array.from(
      new Set(
        topics
          .map((topic) =>
            formatFromTopicType(
              (topic as { topicable_type?: string }).topicable_type
            )
          )
          .filter((f): f is FormatKey => f !== null)
      )
    );
    const minutes =
      topics.reduce((sum, topic) => sum + durationToMinutes(topic.duration), 0) ||
      durationToMinutes(lesson.duration);
    return {
      id: lesson.id,
      title: lesson.title,
      summary: lesson.summary,
      formats,
      topicCount: topics.length,
      minutes,
    };
  });

/**
 * Public tenant data for a landing page. Every field falls back to an empty
 * list, so pages render the brief copy until the tenant has been seeded.
 *
 * @param courseTitleHint case-insensitive part of the demo course title
 */
export function useLandingData(courseTitleHint: string): LandingData {
  const { apiUrl, settings } = useContext(UlamsContext);
  const [state, setState] = useState<
    Omit<LandingData, "companyName" | "currency" | "courseHref" | "coursePrice">
  >({
    loading: true,
    courses: [],
    lessons: [],
    tutors: [],
    webinars: [],
    events: [],
    products: [],
    consultations: [],
  });

  useEffect(() => {
    if (!apiUrl) return;
    const controller = new AbortController();
    const options = { signal: controller.signal };
    let alive = true;

    (async () => {
      const [courses, tutors, webinars, events, products, consultations] =
        await Promise.all([
          settle<API.Course>(fetchCourses(apiUrl, { per_page: 12 }, options)),
          settle<API.UserItem>(fetchTutors(apiUrl, options)),
          settle<API.Webinar>(fetchWebinars(apiUrl, { per_page: 6 }, options)),
          settle<API.StationaryEvent>(
            fetchStationaryEvents(apiUrl, { per_page: 6 }, options)
          ),
          settle<API.Product>(fetchProducts(apiUrl, { per_page: 12 }, options)),
          settle<API.Consultation>(
            fetchConsultations(apiUrl, { per_page: 6 }, options)
          ),
        ]);

      const hint = courseTitleHint.toLowerCase();
      const listed =
        courses.find((c) => c.title?.toLowerCase().includes(hint)) ?? courses[0];

      let detailed: API.Course | undefined = listed;
      if (listed) {
        try {
          const response = await getCourse(apiUrl, listed.id, null, options);
          if (response && response.success) detailed = response.data;
        } catch {
          // keep the list item; the syllabus falls back to the brief
        }
      }

      if (!alive) return;
      setState({
        loading: false,
        courses,
        course: detailed,
        lessons: lessonsFromCourse(detailed),
        tutors,
        webinars: webinars.filter((w) => !w.is_ended),
        events: events.filter((e) => !e.is_ended),
        products,
        consultations,
      });
    })();

    return () => {
      alive = false;
      controller.abort();
    };
  }, [apiUrl, courseTitleHint]);

  return useMemo(() => {
    const value = settings?.value as API.AppSettings | undefined;
    const currency: string = value?.currencies?.default || "EUR";
    const course = state.course;
    const price =
      course?.product?.gross_price ?? course?.product?.price ?? course?.base_price;
    return {
      ...state,
      companyName: value?.global?.companyName || undefined,
      currency,
      courseHref: course
        ? routeRoutes.course.replace(":id", String(course.id))
        : routeRoutes.courses,
      coursePrice: price ? formatMoney(Number(price), currency) : null,
    };
  }, [state, settings?.value]);
}

/** Link to a webinar page, or the list when the webinar is unknown. */
export const webinarHref = (webinar?: API.Webinar): string =>
  webinar ? routeRoutes.webinar.replace(":id", String(webinar.id)) : routeRoutes.webinars;

/** Product page link, or null. */
export const productHref = (product?: API.Product): string | null =>
  product?.id ? routeRoutes.packageProduct.replace(":id", String(product.id)) : null;

export const fullName = (user: API.UserItem): string =>
  [user.first_name, user.last_name].filter(Boolean).join(" ") ||
  (user as { name?: string }).name ||
  "";
