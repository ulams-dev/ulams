import {
  asFiles,
  checkFiles,
  exerciseFields,
  exercisePreviewHref,
  exerciseProblems,
  isCodeExercisesEnabled,
  LIMITS,
  STARTER,
} from './codeExercise';

const file = (path: string, content = 'x') => ({ path, content });

describe('code exercise service', () => {
  it('accepts the starter exercise', () => {
    expect(checkFiles(STARTER.files, 'javascript', false)).toEqual([]);
    expect(checkFiles(STARTER.tests, 'javascript', true)).toEqual([]);
    expect(
      exerciseProblems({
        language: 'javascript',
        files: STARTER.files,
        visible_tests: STARTER.tests,
      }),
    ).toEqual([]);
  });

  it.each([
    ['a traversal', [file('../a.js')]],
    ['an absolute path', [file('/etc/a.js')]],
    ['a hidden file', [file('.env.js')]],
    ['a hidden folder', [file('.git/a.js')]],
    ['spaces', [file('my file.js')]],
    ['backslashes', [file('a\\b.js')]],
    ['a php file', [file('a.php')]],
    ['an html file', [file('index.html')]],
    ['no extension', [file('Makefile')]],
    ['a ts file in javascript', [file('a.ts')]],
    ['the same path twice', [file('a.js'), file('A.js')]],
    ['a null byte', [file('a.js', 'a\0b')]],
    ['a file over 64 KB', [file('a.js', 'a'.repeat(LIMITS.maxFileBytes + 1))]],
    ['a long path', [file(`${'a'.repeat(90)}.js`)]],
    ['no files', []],
    ['more than 20 files', Array.from({ length: 21 }, (_, i) => file(`f${i}.js`))],
    [
      'more than 256 KB together',
      Array.from({ length: 5 }, (_, i) => file(`f${i}.js`, 'a'.repeat(60000))),
    ],
  ])('refuses %s', (_label, files) => {
    expect(checkFiles(files, 'javascript', false).length).toBeGreaterThan(0);
  });

  it('knows the test file rules and the languages', () => {
    expect(checkFiles([file('a.json')], 'javascript', true).length).toBeGreaterThan(0);
    expect(checkFiles([file('a.test.ts')], 'typescript', true)).toEqual([]);
    expect(checkFiles([file('a.ts')], 'typescript', false)).toEqual([]);
    expect(
      checkFiles(
        Array.from({ length: 11 }, (_, i) => file(`t${i}.test.js`)),
        'javascript',
        true,
      ).length,
    ).toBeGreaterThan(0);
  });

  it('reads files from a list or a JSON string and sends lists as JSON strings', () => {
    expect(asFiles(JSON.stringify(STARTER.files))).toEqual(STARTER.files);
    expect(asFiles('{not json')).toEqual([]);
    expect(asFiles(undefined)).toEqual([]);
    const fields = exerciseFields({
      language: 'typescript',
      files: STARTER.files,
      visible_tests: STARTER.tests,
      text: 'Task',
    });
    expect(fields).toMatchObject({
      language: 'typescript',
      timeout_ms: 5000,
      completion_rule: 'on_pass',
      pass_score: 100,
      text: 'Task',
    });
    expect(JSON.parse(fields.files as string)).toEqual(STARTER.files);
  });

  it('checks the timeout and the pass score', () => {
    const base = { files: STARTER.files, visible_tests: STARTER.tests };
    expect(exerciseProblems({ ...base, timeout_ms: 100 })).toEqual([
      'timeout: between 500 and 30000 ms',
    ]);
    expect(exerciseProblems({ ...base, timeout_ms: 60000 })).toHaveLength(1);
    expect(exerciseProblems({ ...base, pass_score: 0 })).toEqual(['pass score: between 1 and 100']);
    expect(exerciseProblems({ ...base, files: [] })[0]).toMatch(/^files: /);
  });

  it('builds the preview link only for a saved topic and follows the tenant switch', () => {
    expect(exercisePreviewHref('http://acme.app.localhost:4321/', 3, 9)).toBe(
      'http://acme.app.localhost:4321/preview/courses/3/9',
    );
    expect(exercisePreviewHref(null, 3, 9)).toBeNull();
    expect(exercisePreviewHref('http://x', 3, undefined)).toBeNull();
    expect(isCodeExercisesEnabled(undefined)).toBe(true);
    expect(isCodeExercisesEnabled({ ulams_code_exercises: { enabled: false } })).toBe(false);
    expect(isCodeExercisesEnabled({ ulams_code_exercises: { enabled: 'false' } })).toBe(false);
  });
});
