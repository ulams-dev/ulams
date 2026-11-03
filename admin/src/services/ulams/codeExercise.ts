/**
 * Code exercise topics (api/packages/code-exercises, ADR 0170): starter files and visible tests the learner edits and
 * runs in the browser sandbox. The files are checked here with the limits the API enforces (ExerciseFilesValidator),
 * so the editor shows problems before saving; the API checks everything again, because anything that reaches it is
 * untrusted input.
 */

export type CodeLanguage = 'javascript' | 'typescript';
export type CodeCompletionRule = 'on_pass' | 'on_attempt';
export type ExerciseFile = { path: string; content: string };

export type CodeExerciseTopicable = {
  id?: number;
  language?: CodeLanguage;
  files?: ExerciseFile[] | string;
  visible_tests?: ExerciseFile[] | string;
  timeout_ms?: number;
  completion_rule?: CodeCompletionRule;
  pass_score?: number;
  text?: string | null;
};

/** The limits of the API (config ulams_code_exercises.limits and .languages). */
export const LIMITS = {
  maxFiles: 20,
  maxTestFiles: 10,
  maxFileBytes: 64 * 1024,
  maxTotalBytes: 256 * 1024,
  maxPathLength: 80,
  timeout: { min: 500, max: 30000, default: 5000 },
};

export const LANGUAGES: Record<
  CodeLanguage,
  { label: string; extensions: string[]; tests: string[] }
> = {
  javascript: {
    label: 'JavaScript',
    extensions: ['js', 'mjs', 'json', 'txt', 'md'],
    tests: ['js', 'mjs'],
  },
  typescript: {
    label: 'TypeScript',
    extensions: ['ts', 'js', 'mjs', 'json', 'txt', 'md'],
    tests: ['ts', 'js', 'mjs'],
  },
};

const DENIED = [/(^|\/)\./, /\.(php\d?|phtml|phar|sh|exe|dll|so|html?|svgz?)$/i];
const PATH = /^[A-Za-z0-9_][A-Za-z0-9_.-]*(\/[A-Za-z0-9_][A-Za-z0-9_.-]*)*$/;

const byteLength = (text: string): number => new TextEncoder().encode(text).length;

/** Starter files and a test for a first exercise. */
export const STARTER: { files: ExerciseFile[]; tests: ExerciseFile[] } = {
  files: [{ path: 'sum.js', content: 'export function sum(a, b) {\n  return 0;\n}\n' }],
  tests: [
    {
      path: 'sum.test.js',
      content:
        "import { sum } from './sum.js';\n\ntest('adds two numbers', () => expect(sum(1, 2)).toBe(3));\ntest('adds negative numbers', () => expect(sum(-1, -2)).toBe(-3));\n",
    },
  ],
};

/** The problems of one list of files, in plain words; an empty list means the files are fine. */
export function checkFiles(
  files: ExerciseFile[],
  language: CodeLanguage,
  tests: boolean,
): string[] {
  const rules = LANGUAGES[language];
  const allowed = tests ? rules.tests : rules.extensions;
  const max = tests ? LIMITS.maxTestFiles : LIMITS.maxFiles;
  if (files.length === 0) {
    return ['needs at least one file'];
  }
  if (files.length > max) {
    return [`has more than ${max} files`];
  }
  const errors: string[] = [];
  const seen = new Set<string>();
  let total = 0;
  files.forEach(({ path, content }, index) => {
    const label = `#${index + 1}${path ? ` (${path})` : ''}`;
    if (path === '' || path.length > LIMITS.maxPathLength) {
      errors.push(`${label}: the path must be 1 to ${LIMITS.maxPathLength} characters`);
      return;
    }
    if (!PATH.test(path) || path.includes('..')) {
      errors.push(
        `${label}: use a relative path of letters, digits, _, - and . with / between folders`,
      );
      return;
    }
    if (DENIED.some((pattern) => pattern.test(path))) {
      errors.push(`${label}: this kind of file is not allowed`);
      return;
    }
    const extension = path.slice(path.lastIndexOf('.') + 1).toLowerCase();
    if (!path.includes('.') || !allowed.includes(extension)) {
      errors.push(`${label}: the extension must be one of ${allowed.join(', ')}`);
    }
    if (seen.has(path.toLowerCase())) {
      errors.push(`${label}: the path is used twice`);
    }
    seen.add(path.toLowerCase());
    if (content.includes('\0')) {
      errors.push(`${label}: the content is not text`);
    }
    const bytes = byteLength(content);
    if (bytes > LIMITS.maxFileBytes) {
      errors.push(`${label}: larger than ${LIMITS.maxFileBytes / 1024} KB`);
    }
    total += bytes;
  });
  if (total > LIMITS.maxTotalBytes) {
    errors.push(`together larger than ${LIMITS.maxTotalBytes / 1024} KB`);
  }
  return errors;
}

/** Files as the API returned them: a list, or the JSON string of one. */
export function asFiles(value: ExerciseFile[] | string | undefined): ExerciseFile[] {
  if (typeof value === 'string') {
    try {
      return asFiles(JSON.parse(value));
    } catch {
      return [];
    }
  }
  return Array.isArray(value)
    ? value.map((f) => ({ path: String(f?.path ?? ''), content: String(f?.content ?? '') }))
    : [];
}

/** The fields the topic form sends: the lists as JSON strings (the topic is posted as multipart form data). */
export function exerciseFields(state: CodeExerciseTopicable): Record<string, unknown> {
  return {
    language: state.language ?? 'javascript',
    files: JSON.stringify(asFiles(state.files)),
    visible_tests: JSON.stringify(asFiles(state.visible_tests)),
    timeout_ms: state.timeout_ms ?? LIMITS.timeout.default,
    completion_rule: state.completion_rule ?? 'on_pass',
    pass_score: state.pass_score ?? 100,
    text: state.text ?? '',
  };
}

/** Whether the exercise can be saved: every list is fine and the timeout is in range. */
export function exerciseProblems(state: CodeExerciseTopicable): string[] {
  const language = state.language ?? 'javascript';
  const problems = [
    ...checkFiles(asFiles(state.files), language, false).map((p) => `files: ${p}`),
    ...checkFiles(asFiles(state.visible_tests), language, true).map((p) => `tests: ${p}`),
  ];
  const timeout = state.timeout_ms ?? LIMITS.timeout.default;
  if (!Number.isInteger(timeout) || timeout < LIMITS.timeout.min || timeout > LIMITS.timeout.max) {
    problems.push(`timeout: between ${LIMITS.timeout.min} and ${LIMITS.timeout.max} ms`);
  }
  const score = state.pass_score ?? 100;
  if (!Number.isInteger(score) || score < 1 || score > 100) {
    problems.push('pass score: between 1 and 100');
  }
  return problems;
}

/** Link to the learner site's preview of a saved topic (the exercise opens there without tracking anything). */
export function exercisePreviewHref(
  learnerUrl: string | null | undefined,
  courseId?: number,
  topicId?: number,
): string | null {
  if (!learnerUrl || !courseId || !topicId) {
    return null;
  }
  return `${learnerUrl.replace(/\/+$/, '')}/preview/courses/${courseId}/${topicId}`;
}

/** Whether the tenant switch `ulams_code_exercises.enabled` (public config, default on) is on. */
export const isCodeExercisesEnabled = (publicConfig: unknown): boolean => {
  const section = (publicConfig as Record<string, unknown> | undefined)?.ulams_code_exercises;
  const value = (section as Record<string, unknown> | undefined)?.enabled;

  return !(value === false || value === 'false' || value === '0' || value === 0);
};
