import type {
  ApiPath,
  Course,
  CourseProgressSummary,
  DemoRole,
  Envelope,
  LoginResult,
  Paginated,
  Product,
  Profile,
  PublicConfig,
  PublicSettings,
  QuizAnswerValue,
  QuizAttempt,
  StationaryEvent,
  StationaryEventDetail,
  Consultation,
  WebinarDetail,
  Topic,
  TopicProgress,
  ProgressStatus,
  UserSummary,
  Webinar,
} from "./types.ts";

export interface ClientOptions {
  /** Tenant API origin, e.g. `http://coffee.localhost`, or a same-origin proxy prefix such as `/bff`. */
  baseUrl: string;
  /** Passport bearer token. Leave empty in the browser when a server proxy adds it. */
  token?: string | null;
  /** Custom fetch (tests, caching layers). Defaults to the global fetch. */
  fetch?: typeof fetch;
  /** Sent as `Current-timezone`; the quiz and progress APIs use it. */
  timezone?: string;
  /** Abort a request after this many milliseconds (default 15 000). */
  timeoutMs?: number;
  /** Extra headers on every request (e.g. `Host` forwarding is not possible with fetch; use baseUrl). */
  headers?: Record<string, string>;
}

export interface RequestOptions {
  params?: Record<string, string | number>;
  query?: Record<string, string | number | boolean | undefined | null>;
  body?: unknown;
  /** Multipart body (file uploads). Sent as-is; the browser or Node sets the boundary header. */
  form?: FormData;
  signal?: AbortSignal;
}

export type HttpMethod = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

/** Non-2xx answer from the API. `status` 0 means the request did not reach it. */
export class ApiError extends Error {
  readonly status: number;
  readonly path: string;
  readonly body: unknown;

  constructor(status: number, path: string, body: unknown, message?: string) {
    super(message ?? `API ${status} on ${path}`);
    this.name = "ApiError";
    this.status = status;
    this.path = path;
    this.body = body;
  }
}

/** Fills `{name}` placeholders of a documented path. */
export function buildPath(path: string, params: Record<string, string | number> = {}): string {
  return path.replace(/\{(\w+)\}/g, (_, key: string) => {
    const value = params[key];
    if (value === undefined) throw new Error(`Missing path parameter "${key}" for ${path}`);
    return encodeURIComponent(String(value));
  });
}

function buildQuery(query: RequestOptions["query"]): string {
  if (!query) return "";
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null) continue;
    search.set(key, typeof value === "boolean" ? (value ? "1" : "0") : String(value));
  }
  const text = search.toString();
  return text ? `?${text}` : "";
}

const defaultTimezone = (): string => {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
  } catch {
    return "UTC";
  }
};

export type UlamsClient = ReturnType<typeof createClient>;

/**
 * Creates an API client. Every method returns the unwrapped `data` of the API's
 * `{success, message, data}` envelope and throws `ApiError` otherwise.
 */
export function createClient(options: ClientOptions) {
  const baseUrl = options.baseUrl.replace(/\/+$/, "");
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));
  const timeoutMs = options.timeoutMs ?? 15_000;
  const timezone = options.timezone ?? defaultTimezone();

  /** Sends a request and returns the successful Response; throws ApiError otherwise. */
  async function send(method: HttpMethod, path: ApiPath, opts: RequestOptions, accept: string): Promise<Response> {
    const url = `${baseUrl}${buildPath(path, opts.params)}${buildQuery(opts.query)}`;
    const headers: Record<string, string> = {
      Accept: accept,
      "Current-timezone": timezone,
      ...options.headers,
    };
    if (options.token) headers.Authorization = `Bearer ${options.token}`;
    let body: string | FormData | undefined;
    if (opts.form !== undefined) {
      body = opts.form;
    } else if (opts.body !== undefined) {
      headers["Content-Type"] = "application/json";
      body = JSON.stringify(opts.body);
    }

    const timeout = AbortSignal.timeout(timeoutMs);
    const signal = opts.signal ? AbortSignal.any([opts.signal, timeout]) : timeout;
    let response: Response;
    try {
      response = await doFetch(url, { method, headers, body, signal });
    } catch (error) {
      throw new ApiError(0, path, null, `API unreachable on ${path}: ${(error as Error).message}`);
    }
    if (!response.ok) {
      const text = await response.text();
      let json: unknown = null;
      if (text) {
        try {
          json = JSON.parse(text);
        } catch {
          json = { message: text.slice(0, 200) };
        }
      }
      const message = (json as { message?: string } | null)?.message;
      throw new ApiError(response.status, path, json, message ? `API ${response.status}: ${message}` : undefined);
    }
    return response;
  }

  async function raw<T>(method: HttpMethod, path: ApiPath, opts: RequestOptions = {}): Promise<Envelope<T>> {
    const response = await send(method, path, opts, "application/json");
    const text = await response.text();
    let json: unknown = null;
    if (text) {
      try {
        json = JSON.parse(text);
      } catch {
        json = { message: text.slice(0, 200) };
      }
    }
    return json as Envelope<T>;
  }

  /** Binary download (exports, PDFs): the response body as bytes plus its content type and file name. */
  async function download(method: HttpMethod, path: ApiPath, opts: RequestOptions = {}) {
    const response = await send(method, path, opts, "*/*");
    const disposition = response.headers.get("content-disposition") ?? "";
    const filename = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)?.[1] ?? null;
    return {
      data: new Uint8Array(await response.arrayBuffer()),
      contentType: response.headers.get("content-type"),
      filename: filename ? decodeURIComponent(filename) : null,
    };
  }

  async function request<T>(method: HttpMethod, path: ApiPath, opts?: RequestOptions): Promise<T> {
    return (await raw<T>(method, path, opts)).data;
  }

  return {
    baseUrl,
    raw,
    request,
    download,
    /** A copy of this client that sends `token`. */
    withToken: (token: string | null) => createClient({ ...options, token }),

    auth: {
      login: (email: string, password: string, rememberMe = true) =>
        request<LoginResult>("POST", "/api/auth/login", {
          body: { email, password, remember_me: rememberMe ? 1 : 0 },
        }),
      /**
       * Demo mode (api/packages/demo): logs in as the tenant's seeded student or admin.
       * 404 when demo mode is off on the tenant.
       */
      demoLogin: (role: DemoRole = "student") =>
        request<LoginResult>("POST", "/api/demo/login" as ApiPath, { body: { role } }),
      me: () => request<Profile>("GET", "/api/profile/me"),
      logout: () => request<unknown>("POST", "/api/auth/logout"),
    },

    settings: {
      public: () => request<PublicSettings>("GET", "/api/settings"),
      config: () => request<PublicConfig>("GET", "/api/config"),
    },

    courses: {
      list: (query: { per_page?: number; page?: number; title?: string } = {}) =>
        raw<Course[]>("GET", "/api/courses", { query }).then(
          (envelope): Paginated<Course> => ({ data: envelope.data ?? [], meta: envelope.meta })
        ),
      get: (id: number) => request<Course>("GET", "/api/courses/{id}", { params: { id } }),
      /** Full program with topic content; 403 without access to the course. */
      program: (id: number) => request<Course>("GET", "/api/courses/{id}/program", { params: { id } }),
      /** A topic flagged `preview`, without an account. */
      previewTopic: (courseId: number, topicId: number) =>
        request<Topic>("GET", "/api/courses/{id}/preview/{topic_id}", {
          params: { id: courseId, topic_id: topicId },
        }),
      /** Ids of the courses the user has access to. */
      myIds: () => request<{ ids: number[] }>("GET", "/api/courses/my").then((d) => d.ids ?? []),
      tutors: () => request<UserSummary[]>("GET", "/api/tutors"),
    },

    progress: {
      all: () => request<CourseProgressSummary[]>("GET", "/api/courses/progress"),
      course: (courseId: number) =>
        request<TopicProgress[]>("GET", "/api/courses/progress/{course_id}", { params: { course_id: courseId } }),
      /** Marks a topic as in progress and adds the time since the last ping. */
      ping: (topicId: number) =>
        request<{ status: boolean }>("PUT", "/api/courses/progress/{topic_id}/ping", { params: { topic_id: topicId } }),
      set: (courseId: number, progress: Array<{ topic_id: number; status: ProgressStatus }>) =>
        request<TopicProgress[]>("PATCH", "/api/courses/progress/{course_id}", {
          params: { course_id: courseId },
          body: { progress },
        }),
      complete: (courseId: number, topicId: number) =>
        request<TopicProgress[]>("PATCH", "/api/courses/progress/{course_id}", {
          params: { course_id: courseId },
          body: { progress: [{ topic_id: topicId, status: 1 }] },
        }),
      /** Forwards an H5P xAPI statement. */
      h5p: (topicId: number, statement: unknown) =>
        request<unknown>("POST", "/api/courses/progress/{topic_id}/h5p", {
          params: { topic_id: topicId },
          body: { event: statement },
        }),
    },

    quiz: {
      /** Returns the active attempt or starts a new one. */
      start: (quizId: number) =>
        request<QuizAttempt>("POST", "/api/quiz-attempts", { body: { topic_gift_quiz_id: quizId } }),
      get: (attemptId: number) =>
        request<QuizAttempt>("GET", "/api/quiz-attempts/{id}", { params: { id: attemptId } }),
      list: (quizId: number) =>
        request<QuizAttempt[]>("GET", "/api/quiz-attempts", { query: { topic_gift_quiz_id: quizId, per_page: 25 } }),
      answer: (attemptId: number, questionId: number, answer: QuizAnswerValue) =>
        request<QuizAttempt>("POST", "/api/quiz-answers", {
          body: { topic_gift_quiz_attempt_id: attemptId, topic_gift_question_id: questionId, answer },
        }),
      end: (attemptId: number) =>
        request<QuizAttempt>("POST", "/api/quiz-attempts/{id}/end", { params: { id: attemptId } }),
    },

    events: {
      webinars: (query: { per_page?: number } = {}) => request<Webinar[]>("GET", "/api/webinars", { query }),
      stationary: (query: { per_page?: number } = {}) =>
        request<StationaryEvent[]>("GET", "/api/stationary-events", { query }),
      webinar: (id: number) => request<WebinarDetail>("GET", "/api/webinars/{id}", { params: { id } }),
      stationaryEvent: (id: number) => request<StationaryEventDetail>("GET", "/api/stationary-events/{id}", { params: { id } }),
    },

    consultations: {
      list: (query: { per_page?: number } = {}) => request<Consultation[]>("GET", "/api/consultations", { query }),
      get: (id: number) => request<Consultation>("GET", "/api/consultations/{id}", { params: { id } }),
    },

    products: {
      list: (query: { per_page?: number } = {}) => request<Product[]>("GET", "/api/products", { query }),
    },
  };
}
