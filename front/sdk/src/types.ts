/**
 * Response types of the endpoints the SDK wraps.
 *
 * Request paths are checked against the generated OpenAPI spec (`ApiPath`), but the
 * l5-swagger annotations describe almost no response bodies (they come out as `unknown`),
 * so the response shapes below are written by hand from real API responses.
 * TODO: replace them with generated types once the API documents its responses
 * (ROADMAP-PROMPT 7.3, "OpenAPI spec complete and published").
 */
import type { components, paths } from "./generated/openapi.ts";

/** Every path the API documents, e.g. `/api/courses/{id}/program`. */
export type ApiPath = keyof paths;

/** The documented course schema (partial in the spec). */
export type CourseSchema = components["schemas"]["Course"];

export interface Envelope<T> {
  success: boolean;
  message?: string;
  data: T;
  meta?: PaginationMeta;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface Paginated<T> {
  data: T[];
  meta?: PaginationMeta;
}

export interface LoginResult {
  token: string;
  expires_at?: string | null;
}

export type DemoRole = "student" | "tutor" | "admin";

/** `GET /api/config` → `ulams_demo` (demo package). */
export interface DemoConfig {
  enabled: boolean;
  front_url?: string | null;
  admin_url?: string | null;
}

export interface PublicConfig {
  ulams_auth?: { registration?: string } & Record<string, unknown>;
  ulams_demo?: DemoConfig;
  [group: string]: unknown;
}

/** `GET /api/settings`: public settings, grouped. */
export interface PublicSettings {
  global?: { companyName?: string; frontURL?: string } & Record<string, unknown>;
  theme?: { theme?: string; accent?: string } & Record<string, unknown>;
  currencies?: { default?: string } & Record<string, unknown>;
  [group: string]: unknown;
}

export interface UserSummary {
  id: number;
  first_name: string;
  last_name: string;
  email?: string;
  path_avatar?: string | null;
  url_avatar?: string | null;
  bio?: string | null;
}

export interface Profile extends UserSummary {
  name?: string;
  roles?: string[];
}

export interface Category {
  id: number;
  name: string;
  slug?: string;
}

export interface Tag {
  id: number;
  title: string;
}

export interface Product {
  id: number;
  type?: string;
  name: string;
  description?: string | null;
  /** Minor units (8900 = 89.00). */
  price: number;
  price_old?: number | null;
  purchasable?: boolean;
  limit_total?: number | null;
  subscription_period?: string | null;
  productables?: Array<{ productable_id: number; productable_type: string }>;
}

/** `topicable_type` short names the API uses (last segment of the PHP class). */
export type TopicableClass =
  | "RichText"
  | "Video"
  | "Audio"
  | "Image"
  | "PDF"
  | "OEmbed"
  | "H5P"
  | "ScormSco"
  | "Cmi5Au"
  | "GiftQuiz"
  | "Project";

export type TopicKind =
  | "richtext"
  | "video"
  | "audio"
  | "image"
  | "pdf"
  | "oembed"
  | "h5p"
  | "scorm"
  | "liascript"
  | "lti"
  | "cmi5"
  | "quiz"
  | "project"
  | "unknown";

export interface MediaTopicable {
  id: number;
  value: string;
  url?: string;
  poster?: string | null;
  poster_url?: string | null;
  width?: number | null;
  height?: number | null;
  /** Milliseconds for audio/video. */
  length?: number | null;
}

export interface H5PTopicable {
  id: number;
  value: number | string;
  content?: { id: number | string; title: string; library: string; main_library: string };
}

export interface ScormTopicable {
  id: number;
  value: number;
  uuid?: string;
}

export interface GiftQuizTopicable {
  id: number;
  /** Intro text (Markdown). */
  value: string;
  max_attempts?: number | null;
  /** Minutes. */
  max_execution_time?: number | null;
  min_pass_score?: number | null;
}

export interface Resource {
  id: number;
  name: string;
  url: string;
  path?: string;
}

export interface Topic {
  id: number;
  title: string;
  lesson_id: number;
  active?: boolean;
  preview?: boolean;
  can_skip?: boolean;
  order?: number;
  topicable_id?: number;
  topicable_type: string;
  /** Only in the program and preview responses. */
  topicable?: (MediaTopicable | H5PTopicable | ScormTopicable | GiftQuizTopicable) & Record<string, unknown>;
  summary?: string | null;
  introduction?: string | null;
  description?: string | null;
  duration?: string | null;
  resources?: Resource[];
  json?: { chapters?: Array<{ time: number; title: string }> } & Record<string, unknown>;
}

export interface Lesson {
  id: number;
  title: string;
  summary?: string | null;
  duration?: string | null;
  order?: number;
  active?: boolean;
  topics: Topic[];
  lessons?: Lesson[];
}

export interface Course {
  id: number;
  title: string;
  subtitle?: string | null;
  summary?: string | null;
  description?: string | null;
  level?: string | null;
  language?: string | null;
  duration?: string | null;
  hours_to_complete?: number | null;
  target_group?: string | null;
  status?: string;
  public?: boolean;
  image_url?: string | null;
  poster_url?: string | null;
  video_url?: string | null;
  author?: UserSummary | null;
  authors?: UserSummary[];
  categories?: Category[];
  tags?: Tag[];
  lessons?: Lesson[];
  product?: Product | null;
  users_count?: number;
  /** Free-form model fields (the demo seeders put landing copy here). */
  fields?: Record<string, unknown> | null;
}

/** 0 = not started, 1 = complete, 2 = in progress (`ProgressStatus` enum). */
export type ProgressStatus = 0 | 1 | 2;

export interface TopicProgress {
  topic_id: number;
  status: ProgressStatus;
  seconds?: number | null;
  started_at?: string | null;
  finished_at?: string | null;
}

export interface CourseProgressSummary {
  course: Course;
  progress: TopicProgress[];
  finish_date?: string | null;
  total_spent_time?: number;
}

export interface Webinar {
  id: number;
  name: string;
  description?: string | null;
  short_desc?: string | null;
  active_from?: string | null;
  active_to?: string | null;
  duration?: string | null;
  image_url?: string | null;
  is_ended?: boolean;
  trainers?: UserSummary[];
}

export interface StationaryEvent {
  id: number;
  name: string;
  description?: string | null;
  short_desc?: string | null;
  started_at?: string | null;
  finished_at?: string | null;
  place?: string | null;
  max_participants?: number | null;
  image_url?: string | null;
  is_ended?: boolean;
}

export interface WebinarDetail extends Webinar {
  agenda?: string | null;
  yt_url?: string | null;
  is_started?: boolean;
  in_coming?: boolean;
}

export interface StationaryEventDetail extends StationaryEvent {
  program?: string | null;
  agenda?: string | null;
  authors?: UserSummary[];
}

export interface Consultation {
  id: number;
  name: string;
  description?: string | null;
  short_desc?: string | null;
  duration?: string | null;
  active_from?: string | null;
  active_to?: string | null;
  image_url?: string | null;
  /** ISO start times offered for booking. */
  proposed_terms?: string[];
  busy_terms?: string[];
  author?: UserSummary | null;
  teachers?: UserSummary[];
}

export type QuestionType =
  | "multiple_choice"
  | "multiple_choice_with_multiple_right_answers"
  | "true_false"
  | "short_answers"
  | "matching"
  | "numerical_question"
  | "essay"
  | "description";

export interface QuizQuestion {
  id: number;
  type: QuestionType;
  score: number;
  order?: number;
  title?: string | null;
  question: string;
  options: { answers?: string[]; sub_questions?: string[]; sub_answers?: string[] } | unknown[];
}

/** Body of `answer` in `POST /api/quiz-answers`; the key depends on the question type. */
export type QuizAnswerValue =
  | { text: string }
  | { multiple: string[] }
  | { bool: boolean }
  | { numeric: number }
  | { matching: Record<string, string> }
  | Record<string, never>;

export interface QuizAttemptAnswer {
  id?: number;
  topic_gift_question_id: number;
  answer: QuizAnswerValue;
  score?: number | null;
  feedback?: string | null;
}

export interface QuizAttempt {
  id: number;
  topic_gift_quiz_id: number;
  started_at: string;
  end_at?: string | null;
  max_score: number;
  min_pass_score?: number | null;
  result_score?: number | null;
  result_percent?: number | null;
  is_passed?: boolean | null;
  correct_answers_count?: number | null;
  is_ended: boolean;
  questions: QuizQuestion[];
  answers: QuizAttemptAnswer[];
  topic?: { id: number; title: string };
  course?: { id: number; title: string };
}
