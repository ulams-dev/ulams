# Code exercises

The **code exercise** topic type ([ADR 0170](../../../docs/decisions/0170-code-exercise-runners.md), milestone AX-1): a
topic holds starter files and visible tests; the learner edits the files and runs the tests in the browser, in an
isolated sandbox with no network (JavaScript and TypeScript), and each run is stored as a practice attempt.

- Model `Ulams\CodeExercises\Models\CodeExerciseTopic`, table `topic_code_exercises`: `language`
  (`javascript`, `typescript`), `files` and `visible_tests` (lists of `{path, content}`), `timeout_ms` (500 to
  30000), `completion_rule` (`on_pass`, `on_attempt`), `pass_score`, `text` (Markdown task). `runtime` is
  `browser` and `grading` is `practice` until the server runner exists (AX-4).
- **No admin CRUD.** Topics are created and changed through the topic API (`POST /api/admin/topics`,
  `topicable_type` = `Ulams\CodeExercises\Models\CodeExerciseTopic`; the lists may be JSON strings in multipart
  forms). Permissions and tenant isolation are those of the topic API.
- **Everything an author sends is untrusted input** (`ExerciseFilesValidator`): at most 20 files and 10 test
  files, 64 KB each and 256 KB together, relative paths of plain characters (no `..`, no hidden names, no
  absolute paths), an extension allow-list per language (`config.php`), UTF-8 text without NUL bytes, no
  duplicate paths. The files are stored and sent to the browser as text; this package never runs them.
- **The runner** is a set of static files in `resources/runtime/code-runner/<version>/`, built from
  `front/code-runner` (`yarn workspace @ulams/code-runner build`; CI fails when the committed files are stale)
  and served from the tenant content origin under `/runtime/code-runner/<version>/` by `packages/uploads`
  (`ContentFileController`, prefix `runtime`). `RunnerHeaders` sets the content security policy of each file; the
  two policies are data in `resources/runner-csp.json`, which the front-end tests read as well. `esbuild.wasm`
  (14 MB) is not in git: `bin/fetch-esbuild.sh` downloads it, pinned by checksum, into the image.
- **Results are practice grades.** `POST /api/code-exercises/topics/{topic}/attempts` takes the result of each
  visible test, recomputes the score on the server, stores an attempt with `verified = false`, moves the topic's
  progress by its completion rule (`CourseProgressRepositoryContract`) and dispatches `CodeAttemptFinished`, which
  learner-insights turns into the `code.attempt_finished` signal. Nothing that issues certificates or checks
  compliance reads these attempts as a grade.

## API

| Method | Path | Notes |
|---|---|---|
| POST | `/api/code-exercises/launches/{topic}` | learner (`attend` gate): `{runner: {url, version}, exercise, attempts}`; 404 when disabled or not an exercise, 503 without a content origin |
| POST | `/api/code-exercises/topics/{topic}/attempts` | learner, throttled (`attempts_per_minute`, default 20): `{results: [{name, status: pass\|fail\|error, message?}], files_hash?, runner?, duration_ms?}`; answers `{attempt, status}` |
| GET | `/api/code-exercises/topics/{topic}/attempts` | the caller's own attempts, newest first |
| GET | `/api/admin/code-exercises/topics/{topic}/preview` | author preview, same shape as a launch, nothing tracked |
| GET | `/api/admin/code-exercises/topics/{topic}/attempts` | teacher report: every learner's attempts, paginated |

## Settings

`ulams_code_exercises.enabled` (default on) is an administrable config key; `CODE_EXERCISES_ENABLED` and
`CODE_EXERCISES_RUNTIME_PATH` are environment variables.

## Tests

`./vendor/bin/phpunit --testsuite code-exercises`.
