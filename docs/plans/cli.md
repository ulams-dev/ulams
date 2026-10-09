# Plan: the agent-first `ulams` CLI and MCP server

Status: **draft, waiting for the product owner's approval**. Nothing in this plan is implemented.

The product owner, 2026-10-09: *"I want this [CLI] to be used by agents, so almost everything can be
handled through it, like Cloudflare wrangler."* The owner also decided the order on 2026-10-09: build the
CLI core (login and tokens, the raw `api` command, the main nouns, `ulams mcp`) **now**, course-as-code
**after Phase 3**, with the CLI and MCP expected within about a day (closed issue #73).

Spec: `docs/ROADMAP-PROMPT.md` 7.1 (course-as-code and CLI), 7.3 (API, SDK, webhooks), 7.5 (MCP), principles
6 (developer-first) and 7 (agent-ready). ADRs proposed with this plan: **0072–0079** (section 15).
Owner questions still open: #74, #75, #76, #77, #78, #79; each section that depends on one says
**"default, pending #N"**.

It is written for implementers who were not in the planning session. Every milestone names its files,
tests and definition of done (DoD). Rules from `CLAUDE.md`, `AGENTS.md` and
`docs/plans/leftovers-0-2.md` section 1 apply: Conventional Commits, tests in the same commit, no AI
attribution, docs-site page in the same branch, tenant isolation test and policy for every new endpoint.

---

## 1. Facts this plan is built on (read on `main`, `de5c300a`, 2026-10-09)

| Area | Fact | Where |
|---|---|---|
| API | 61 local packages under `api/packages/*`, each with `src/routes.php`; about 550 routes plus Passport's `/oauth/*` | `api/packages/*/src/routes.php` |
| Auth | Passport 13.9 (`auth:api`), no scopes (`Passport::tokensCan` never called), no endpoint to list or create tokens. Login issues a personal access token valid **5 minutes** (`TokenExpirationEnum::SHORT_TIME_IN_MINUTES`) or one month with `remember_me` | `api/packages/auth/src/Services/AuthService.php:63`, `api/config/auth.php` |
| Device flow | Passport's device grant is disabled (`Passport::$deviceCodeGrantEnabled = false`, commit `e6c21b9e`) | `api/app/Providers/AppServiceProvider.php:44` |
| Tenancy | Host-based (`gecche/laravel-multidomain`, `RejectUnknownHost`); each tenant has its own database and Passport key pair, so a token is valid on one host only. Platform host from `TENANCY_PLATFORM_HOSTS` (`api.localhost`) | `api/packages/tenancy/README.md` |
| Tenant admin | Artisan only: `ulams:tenant:create {slug} --name --theme --accent --users --demo --redo`, `list --hosts`, `set-env`, `sync-env`, `delete --force`, hidden `seed-demo`, `schedule-loop`. No HTTP API | `api/packages/tenancy/src/Console/` |
| Demo | `POST /api/demo/login {role}` when `DEMO_MODE=true`; users `admin@`, `tutor@`, `student1..N@<slug>.ulams.app`, password `TENANT_DEMO_PASSWORD` | `api/packages/demo` |
| OpenAPI | `@OA\` docblocks in 242 files (L0-11 converts them to attributes). The JSON spec is generated to `api/storage/api-docs/api-docs.json` (gitignored). The committed derivative `front/sdk/src/generated/openapi.ts` has **311 paths / 407 operations**. operationIds are **hashes** (swagger-php default), so names must come from method + path | `api/config/l5-swagger.php`, `front/sdk/src/generated/openapi.ts` |
| Response schemas | Of 407 success responses: 124 typed JSON, 212 `application/json: unknown`, 58 with no content, 13 other. About **70 % untyped** | counted in `openapi.ts` |
| Not in OpenAPI | course-builder (24 routes), images, jitsi, pencil-spaces, uploads, youtube, example-plugin (not in the scan paths; L0-11 step 4 adds most) | `api/config/l5-swagger.php` |
| SDK | `@ulams/sdk`, private, exports `.ts` source (no build). `createClient` (JSON only, envelope unwrapping, `ApiError(status, path, body)`), `createCourseBuilderClient` (sessions, sources upload, brief, runs, versions, apply, publish), `ag-ui.ts` (`SseParser`, `connectEventStream`) | `front/sdk/src/*.ts` |
| Course builder | `/api/admin/course-builder/*`, `auth:api` + `EnsureAiEnabled` (503 when AI is off), permission `course_builder_use`. Actions go through `POST sessions/{s}/runs` with `forwardedProps.action.name` ∈ `answer, decide_for_me, approve_outline, reject_outline, approve_patch, reject_patch, approve_apply, retry, retry_step`; element chat = message + `forwardedProps.selection.elementId`. SSE at `sessions/{s}/events` closes after ~25 s, resumes with `Last-Event-ID`. **No run-status endpoint** | `api/packages/course-builder/src/routes.php`, `EventStreamController.php` |
| Blueprint | `api/packages/course-builder/resources/schemas/course-blueprint/v1.json`, `$id https://ulams.dev/schemas/course-blueprint/v1.json`, draft 2020-12: `sources[]`, `course`, `modules[] → lessons[] → blocks[]` (kinds paragraph, callout, steps, code, table, example; `citations` min 1), `quiz`, `finalTest`, `pages`. Lessons are RichText only | same |
| Living Course | Only on the **unpushed local branch** `phase-3/living-course` (32 commits ahead). Routes under `/api/admin/living-course/*` (sources, revisions, connectors, connections, proposals with item accept/reject, apply, staleness, audit) | local branch |
| Uploads | SCORM `POST /api/admin/scorm/upload` field `zip`; cmi5 `POST /api/admin/cmi5` `file`; LiaScript `POST /api/admin/liascript` `file` or `markdown`; H5P `POST /h5p/contents/upload` `h5p_file` (Node service); files `POST /api/admin/file/upload` `target` + `file[]`; course zip `POST /api/admin/courses/zip/import` `file`; topics `POST /api/admin/topics` multipart; topic resources `resource` | per package |
| Webhooks | No outbound webhooks (7.3 not started). Inbound only: Stripe, Jitsi; Living Course source webhooks on the Phase 3 branch | |
| Docs site | Starlight in `front/docs-site`; generated reference pages via `scripts/generators/NN-*.mjs`; coverage check `scripts/coverage.mjs` (modules, admin routes, learner routes, topic types) enforced by `.github/workflows/docs.yml` | |
| npm | `ulams`, `create-ulams`, `@ulams/cli` are unclaimed (404 on 2026-10-09) | registry |
| MCP | Current spec **2026-07-28**: stateless (no `initialize`, no sessions), `server/discover`, Multi Round-Trip Requests (`input_required`) replace server-initiated elicitation, tasks moved to an extension, `ttlMs`/`cacheScope` on list results, any JSON Schema 2020-12 in `inputSchema`/`outputSchema`. TypeScript SDK **v2** (`@modelcontextprotocol/server` / `client` / `node` 2.3.x) implements it; v1 (`@modelcontextprotocol/sdk` 1.32, MIT) gets fixes until at least 2027-01 | modelcontextprotocol.io, npm |

What this means for the design:

1. A CLI token must outlive 5 minutes and must be scoped and revocable: **scoped personal access tokens**
   are a server prerequisite (section 6), but M1 can start with a 1-month `remember_me` token or a pasted
   token.
2. Command names cannot come from operationIds; they come from **method + path** plus a curated override
   file (section 4.4).
3. Typed output is impossible for 70 % of endpoints until response schemas exist. Commands still return
   the API's `data` verbatim; the `output` schema of a generated command is `unknown` until L0-11 and the
   response-schema work fill it (section 6.7).
4. Course builder commands are hand-written on the existing SDK client, not generated.
5. Living Course commands wait for the Phase 3 merge.

---

## 2. Goal and scope

**Goal.** One CLI, `ulams`, that AI agents and humans use to do almost everything the admin, studio and
learner UIs do, and the same command set exposed as an MCP server (`ulams mcp`), generated from one
registry so the two never drift.

**In scope (this plan):**

- `front/cli` workspace: package `ulams`, bin `ulams`, built on `@ulams/sdk`.
- Agent contract: non-interactive by default, `--json`/NDJSON with a versioned schema, stable exit and
  error codes with hints, `--dry-run`, idempotent `apply -f`, `schema`/`describe` introspection, raw
  `ulams api`, pagination, `--wait`/`--no-wait`.
- Auth: `login` (password, token, demo, then device code), profiles per instance and tenant, env vars,
  `whoami`, `tokens`, redaction.
- Commands for: courses, lessons, topics of every type (with uploads), quizzes and questions, categories,
  tags, files, users, groups, roles, permissions, enrolments and access, settings, theme, pages,
  templates, notifications, webinars, stationary events, consultations, certificates (PDF templates),
  orders and products (read), reports and stats, LTI registrations, translations, questionnaires, the
  course builder, Living Course (after Phase 3), tenants (platform admins).
- `ulams mcp` (stdio and Streamable HTTP) with annotations, toolsets, confirmation for destructive tools,
  resources.
- Server work the CLI needs: scoped tokens with an agent audit log and `Idempotency-Key`, device login,
  run-status endpoint, capabilities endpoint, platform tenant API, OpenAPI completeness (via L0-11).
- Course-as-code (`init`, `validate`, `preview`, `push`, `pull`, `diff`, `publish`), **after Phase 3**.
- Coverage matrix, generated docs, agent guide, eval with a real model.
- Distribution: npm, standalone binaries, Docker image. No telemetry.

**Out of scope:** outbound webhooks (7.3; the CLI gets `ulams webhooks …` when they exist, see 7.9),
the hosted remote MCP server and MCP OAuth (later; pending #75), A2UI over MCP (7.5, later), the GitHub
Action and preview deployments (7.1, separate plan after M5), learner-facing MCP tools beyond what the
learner nouns give (7.5, later), the PHP SDK.

---

## 3. Milestones at a glance (fastest useful result first)

| # | Milestone | Demo | Depends on | Size |
|---|---|---|---|---|
| **M1** | Core: workspace, registry, parser, output contract, profiles, `login` (password/token/demo), `whoami`, `api`, `schema`, `describe` | `ulams login --demo admin --url http://coffee.localhost && ulams api GET /api/admin/courses --json` | nothing | ~4 h |
| **M2** | Nouns generated from OpenAPI + curated overrides, uploads, pagination, `--dry-run`, `--wait`, `apply -f`, coverage matrix | `ulams courses create --title "Kubernetes 101" --json`, `ulams topics upload-scorm --lesson 12 ./pkg.zip`, `ulams apply -f course.yaml --dry-run` | M1 | ~6 h |
| **M3** | `ulams mcp` (stdio + HTTP), toolsets, confirmation, resources; docs generator; agent guide; eval | Claude Code with `ulams mcp` creates a course with two lessons and a quiz, publishes it, enrols a student | M2 | ~4 h |
| **M4** | Course builder commands + AG-UI event stream as NDJSON + run status; Living Course commands once Phase 3 merges | `ulams builder create --from ./guide.md --wait --answers answers.yaml --apply` | M1 (+ S4); Living Course part needs Phase 3 | ~4 h + ~3 h |
| **M5** | Course-as-code: format, `init`, `validate`, `preview`, `push`, `pull`, `diff`, `publish`, two-way sync | `ulams init && ulams push && (edit in UI) && ulams pull` shows a diff | Phase 3 merged, M4 | ~2 days |
| **M6** | Ship: npm publish, bun binaries, Docker image, release workflow | `npx ulams --version`, `docker run ghcr.io/ulams-dev/ulams-cli` | M3; #76, #77 | ~3 h |

Server track (parallel with M1–M3, separate PRs, PHP):

| # | Server work | Unblocks | Size |
|---|---|---|---|
| **S1** | Scoped personal access tokens, scope enforcement, agent audit log, `Idempotency-Key`, `X-Request-Id`, `GET /api/meta` capabilities | `ulams tokens`, least-privilege agents, safe retries | ~6 h |
| **S2** | Device login (own RFC 8628 flow) + approval page in the web app + admin "API tokens" page | `ulams login` without a password (pending #74) | ~5 h |
| **S3** | Platform tenant API (queued provisioning, operation status) | `ulams tenants …` remotely (pending #79) | ~5 h |
| **S4** | Course builder run-status endpoint `GET /api/admin/course-builder/runs/{run}` | `--wait` and `operations get` for builder runs | ~1 h |
| **S5** | L0-11 (OpenAPI attributes, scan paths) + response schemas for the top 60 operations the nouns use | typed output, MCP `outputSchema` | per `leftovers-0-2.md` + ~1 day |

The "within about a day" expectation is met by **M1 + M2 + M3** on the CLI side, using password, demo or
pasted tokens; S1 and S2 make the auth production-grade and can land the same or the next day. Agents
should run M1 first (alone, it fixes the contracts every other piece uses), then M2 and S1/S4 in
parallel, then M3 and S2/S3 in parallel.

---

## 4. Architecture

### 4.1 Workspace layout

```
front/cli/                         package "ulams", bin "ulams", MIT
  package.json                     type module, engines node >=22.12, bin { ulams: dist/ulams.mjs }
  tsconfig.json  eslint.config.mjs  vitest.config.ts  tsup.config.ts
  spec/openapi.json                normalised snapshot of the API spec (committed; section 4.4)
  spec/overrides.yaml              curated names, hidden ops, verb fixes, upload fields, scopes, kinds
  spec/exclusions.yaml             operations deliberately without a noun command, with a reason
  scripts/sync-spec.mjs            copies + normalises api/storage/api-docs/api-docs.json → spec/openapi.json
  scripts/gen-commands.mjs         spec + overrides → src/generated/commands.ts (zod schemas as source)
  scripts/coverage.mjs             operations vs registry → coverage/matrix.json + markdown; exit 1 on gaps
  src/
    bin.ts                         entry: run(process.argv) → exit code
    registry/types.ts              CommandDef, Ctx, Result (section 4.2)
    registry/index.ts              all commands: generated + hand-written, de-duplicated by id
    registry/schema-export.ts      registry → JSON (for `schema`, MCP, docs)
    cli/parse.ts                   argv → {command, input, globals} using node:util parseArgs
    cli/help.ts                    help text from the registry
    cli/run.ts                     resolve profile → client → validate → dryRun/confirm → run → render
    output/envelope.ts render.ts table.ts ndjson.ts   (section 4.5)
    errors.ts exit-codes.ts        (section 4.6)
    config/paths.ts profiles.ts credentials.ts redact.ts
    http/client.ts                 wraps @ulams/sdk createClient + multipart + request id + idempotency
    http/pagination.ts lro.ts sse.ts
    commands/core/*.ts             login logout whoami profiles config api schema describe version completion
    commands/tokens.ts apply.ts operations.ts
    commands/builder/*.ts          (M4)  commands/living/*.ts (M4b)  commands/course/*.ts (M5)
    commands/tenants.ts            (S3)
    mcp/server.ts tools.ts resources.ts confirm.ts   (M3)
    generated/commands.ts          generated, committed, lint-ignored
  tests/                           unit (mocked fetch), contract, mcp, e2e (opt-in)
  coverage/matrix.json  coverage/matrix.md   generated, committed
```

`@ulams/sdk` exports TypeScript source; the CLI imports it as a workspace dependency and **tsup bundles it**
into `dist/ulams.mjs` (the SDK stays private). Add `front/cli` to the root `package.json` workspaces and to
the root `test` script filter; add `cli: "front/cli (ulams CLI)"` to `APPS` in
`front/docs-site/scripts/coverage.mjs`.

Runtime rule: **commands use only `fetch`, `AbortSignal`, `TextEncoder`, `crypto.subtle`, `Blob`/`FormData`**
from the platform. File system, process and TTY access are passed in through `Ctx` (section 4.2). This keeps
the registry runnable inside the MCP server, under bun-compiled binaries and, later, inside a Worker
(pending #75).

### 4.2 The command registry (one source of truth)

```ts
// src/registry/types.ts
import type { z } from "zod";

export type Kind = "read" | "write" | "destructive" | "stream" | "local";
export type Audience = "admin" | "author" | "learner" | "platform" | "any";

export interface Example { title: string; argv: string; output?: unknown }

export interface CommandDef<I extends z.ZodObject = z.ZodObject, O extends z.ZodType = z.ZodType> {
  id: string;                    // "courses.list"; CLI path "courses list"; MCP tool "courses_list"
  summary: string;               // one line, imperative, used by help, describe and MCP description
  description?: string;          // when/why, pitfalls; written for models (see 8.6)
  input: I;                      // zod object; every prompt is a field
  positionals?: Array<keyof z.infer<I>>;   // e.g. ["id"] → `ulams courses get 12`
  output: O;                     // schema of envelope.data; z.unknown() when the API has none
  kind: Kind;
  idempotent: boolean;           // safe to retry with the same input
  scopes: string[];              // token scopes required (section 6.2), e.g. ["courses:write"]
  audience: Audience[];
  endpoints: string[];           // "POST /api/admin/courses", used by the coverage matrix
  paginated?: boolean;           // adds --page --per-page --all --limit
  longRunning?: LongRunning;     // adds --wait/--no-wait/--timeout (section 4.8)
  dryRun?: "client" | "server" | "none";   // default "client" for write/destructive
  upload?: Record<string, { field: string; accept?: string[] }>; // input key → multipart field
  mcp?: { expose?: boolean; toolset?: string; title?: string };  // default expose true, toolset from noun
  stability: "stable" | "beta" | "experimental";
  since: string;                 // CLI version that added it
  examples: Example[];           // at least one; docs and describe show them
  run(ctx: Ctx, input: z.infer<I>): Promise<Result<z.infer<O>>>;
  plan?(ctx: Ctx, input: z.infer<I>): Promise<Plan>;  // dry-run preview (section 4.7)
}

export interface Ctx {
  client: HttpClient;            // section 4.9
  profile: ResolvedProfile;      // url, tenant label, token source (never the token in logs)
  fs: FsPort;                    // readFile, writeFile, stat, glob; MCP HTTP mode gives a deny-all port
  io: { stderr(line: string): void; isTTY: boolean; interactive: boolean };
  signal: AbortSignal;
  emit(event: unknown): void;    // stream commands: one NDJSON line / one MCP progress notification
  flags: GlobalFlags;
}

export interface Result<T> { data: T; meta?: PageMeta | Record<string, unknown>; warnings?: Warning[] }
```

From this one registry:

| Consumer | How |
|---|---|
| CLI parser | `cli/parse.ts` maps zod fields to flags (rules in 4.3) |
| Help | `ulams <noun> --help`, `ulams <noun> <verb> --help` rendered from summary, fields (`.describe()`), examples |
| `ulams schema` | full registry as JSON: id, path, summary, input JSON Schema (`z.toJSONSchema`, draft 2020-12), output JSON Schema, kind, scopes, examples, contract version |
| `ulams describe <command>` | one entry of the same JSON, plus flag spellings and an example invocation |
| MCP tools | `mcp/tools.ts`: name `id.replace(".", "_")`, `inputSchema`, `outputSchema`, annotations from `kind` and `idempotent` (section 8) |
| Docs pages | `front/docs-site/scripts/generators/70-cli.mjs` reads `ulams schema --json` output (section 10) |
| Coverage | `scripts/coverage.mjs` compares `endpoints` with `spec/openapi.json` (section 4.10) |

Validation: `input.safeParse(merged)` before any request; failures exit 2 with `INPUT_INVALID` and the zod
issues mapped to `{path, message}` in `error.details`.

### 4.3 Flags, positionals and input

- Nouns are plural and kebab-case: `courses`, `lessons`, `topics`, `quizzes`, `questions`, `categories`,
  `tags`, `files`, `users`, `groups`, `roles`, `permissions`, `access`, `settings`, `theme`, `pages`,
  `templates`, `notifications`, `webinars`, `events`, `consultations`, `certificates`, `orders`,
  `products`, `reports`, `stats`, `lti`, `translations`, `questionnaires`, `tokens`, `builder`, `living`,
  `tenants`, `operations`, `profiles`.
- Verbs, in this order of preference: `list`, `get`, `create`, `update`, `delete`, then domain verbs
  (`publish`, `unpublish`, `clone`, `export`, `import`, `upload`, `add-member`, `remove-member`, `grant`,
  `revoke`, `approve`, `reject`, `apply`, `cancel`, `wait`).
- Field `courseId` → flag `--course-id`; booleans `--flag` / `--no-flag`; arrays repeat
  (`--tag a --tag b`) or take JSON (`--tags '["a","b"]'`); enums are validated; nested objects take JSON
  or `--set path.to.key=value` (repeatable).
- Every command accepts `--input <json | @file.json | @file.yaml | ->` with the whole input object.
  Explicit flags override `--input` fields. This is the recommended form for agents (no quoting
  surprises).
- Positionals only for the primary id(s) (`ulams courses get 12`); every positional also exists as a flag.
- **Never prompt** unless `stdin` and `stderr` are both TTYs **and** `--interactive` is given or the
  command is `login`. Missing required input in non-interactive mode exits 2 with `INPUT_INVALID` and a
  hint naming the flag.

Global flags (registry `GlobalFlags`, documented once):

| Flag | Env | Meaning |
|---|---|---|
| `--profile <name>` | `ULAMS_PROFILE` | profile to use |
| `--url <origin>` | `ULAMS_URL` | tenant or platform API origin, overrides the profile |
| `--token-stdin` | `ULAMS_TOKEN` | read a token from stdin (no `--token` flag: it would leak into shell history and `ps`) |
| `--json` | | shorthand for `--output json` |
| `--output json\|ndjson\|yaml\|table\|text` | `ULAMS_OUTPUT` | default `auto`: `table`/`text` on a TTY, `json` when stdout is not a TTY |
| `--fields a,b.c` | | project `data` (each item for lists) to these paths |
| `--quiet` | | no stderr progress |
| `--no-color` | `NO_COLOR` | |
| `--dry-run` | | plan only, no mutation (section 4.7) |
| `--yes` | `ULAMS_YES=1` | confirm destructive commands non-interactively |
| `--wait` / `--no-wait`, `--timeout <s>` | | long-running operations (4.8); default wait, 600 s |
| `--idempotency-key <key>` | | sent as `Idempotency-Key` (S1); default: generated per invocation for POST |
| `--page`, `--per-page`, `--all`, `--limit <n>` | | pagination (4.8) |
| `--debug` | `ULAMS_DEBUG=1` | redacted request/response log on stderr |
| `--interactive` | | allow prompts |

Precedence: flag > env > profile > default.

### 4.4 Generated noun commands (M2)

`scripts/sync-spec.mjs`:
1. Runs nothing itself; expects `api/storage/api-docs/api-docs.json` (generate with
   `docker compose -f api/docker-compose.yml exec api php artisan l5-swagger:generate`).
2. Normalises (sort keys, drop `servers`, strip descriptions' trailing spaces) and writes
   `front/cli/spec/openapi.json`. Committed. Until L0-11 lands, the course-builder paths are missing; that
   is fine because builder commands are hand-written.

`scripts/gen-commands.mjs` (pure Node, no dependencies beyond `yaml`):
1. For every operation, derive the default id from the path:
   - drop `/api`, drop the `admin` segment but remember it (`audience: admin`), drop path parameters
     from the noun;
   - noun = first remaining segment, kebab-case, plural as in the API (`courses`, `user-groups` →
     override to `groups`);
   - verb: `GET` collection → `list`; `GET` item → `get`; `POST` collection → `create`; `PUT`/`PATCH`
     item → `update`; `DELETE` item → `delete`; any other trailing literal segment → that segment as the
     verb (`POST /admin/courses/{course}/clone` → `courses.clone`); sub-collections become
     `noun.sub-verb` (`GET /admin/users/{id}/settings` → `users.settings-get`).
   - Learner endpoints (no `admin`) get the prefix `my.` when they are about the current user
     (`/api/courses/progress` → `my.progress-list`) or keep the noun with `audience: learner`
     (`/api/courses` → `catalog.courses-list`). Overrides fix the rest.
2. Apply `spec/overrides.yaml` (keyed by `METHOD /path`):
   ```yaml
   "GET /api/admin/user-groups": { id: groups.list }
   "POST /api/admin/topics": { id: topics.create, upload: { value.file: { field: value[file] } } }
   "POST /api/admin/topics/{topic}": { id: topics.update }          # multipart "update" via POST
   "POST /api/admin/scorm/upload": { id: scorm.upload, upload: { zip: { field: zip, accept: [.zip] } }, longRunning: false }
   "DELETE /api/admin/courses/{course}": { kind: destructive }
   "POST /api/admin/courses/{id}/access/set": { id: access.set, kind: destructive }
   "GET /api/admin/stats/course/{course_id}/export": { id: stats.course-export, output: file }
   "POST /api/auth/login": { hide: true }                           # covered by `login`
   ```
   Keys: `id`, `hide`, `kind`, `idempotent`, `scopes`, `positionals`, `upload`, `paginated`,
   `longRunning`, `summary`, `description`, `examples`, `output: file` (binary download to `--out`).
3. Build the zod input from path params (required), query params and the JSON or multipart body schema.
   Mapping: `string` → `z.string()` (+ `format: date-time` → `.datetime()` only as describe text, not
   validation, because the API is lenient), `integer` → `z.number().int()`, `number`, `boolean`,
   `enum` → `z.enum`, `array` → `z.array`, `object` with properties → `z.object(...).passthrough()`,
   anything unresolved → `z.unknown()`. `$ref` resolved inline (depth limit 6; cycles become
   `z.unknown()`). Required lists honoured. Every field keeps its OpenAPI description via `.describe()`.
4. Output: the 2xx schema mapped the same way when present, else `z.unknown()`.
5. Kind: `GET` → read, `DELETE` → destructive, other → write; overrides win. Idempotent: `GET`, `PUT`,
   `DELETE` true; `POST`/`PATCH` false.
6. Scopes: from the route → scope map (6.2) exported by S1 as `api/packages/auth/resources/token-scopes.json`;
   before S1 lands, derive from the noun (`courses:read|write`).
7. Emit `src/generated/commands.ts` as **TypeScript source with zod expressions** (deterministic order),
   each `run` calling `ctx.client.call(method, path, {params, query, body, files})`.
8. Duplicate ids fail the generator with a list; resolve them in overrides.

Hand-written commands (in `src/commands/**`) win over generated ones with the same id and must list the
same `endpoints`. Write hand-written versions only where the generic one is unusable: topics of each type
(4.11), access/enrolment, settings/theme, builder, living, tenants, tokens, apply.

### 4.5 Output contract (ADR 0073)

JSON mode prints exactly one document on **stdout**:

```json
{
  "ok": true,
  "contract": 1,
  "command": "courses.list",
  "data": [ { "id": 12, "title": "Kubernetes 101", "status": "draft" } ],
  "meta": { "page": 1, "perPage": 25, "total": 120, "lastPage": 5, "nextPage": 2 },
  "warnings": [ { "code": "UNTYPED_OUTPUT", "message": "The API documents no response schema for this command." } ]
}
```

Errors (any mode, JSON mode on stdout, human mode on stderr):

```json
{
  "ok": false,
  "contract": 1,
  "command": "courses.get",
  "error": {
    "code": "NOT_FOUND",
    "message": "Course 999 does not exist on coffee.localhost.",
    "hint": "List course ids with `ulams courses list --fields id,title`.",
    "status": 404,
    "retryable": false,
    "requestId": "01J9Z…",
    "details": {}
  }
}
```

- `data` is the API envelope's `data`, unchanged (no renaming), so `ulams api` and noun commands agree.
- `meta` normalises the Laravel paginator (`current_page`, `per_page`, `total`, `last_page`) to camelCase.
- NDJSON (`--output ndjson`, streams, `--all` lists): one JSON object per line. List items are
  `{"type":"item","data":{…}}`; stream events `{"type":"event","data":{…AG-UI event…}}`; progress
  `{"type":"progress","data":{"step":"…","status":"…"}}`; the last line is the normal envelope without
  `data` for lists (`{"type":"end","ok":true,"meta":{…}}`) or the error envelope.
- `contract` is an integer. Additive changes keep it; removing or renaming a field bumps it and needs an
  ADR. `ulams schema` includes the JSON Schema of the envelope itself.
- Human mode: tables for lists (columns from `--fields`, else `id`, `title|name|email`, `status`,
  `updated_at` when present), YAML-ish key/values for objects, colours only on TTY.
- stderr carries progress and logs only; agents can ignore it.

### 4.6 Exit codes and error codes (ADR 0073)

| Exit | Error codes | When |
|---|---|---|
| 0 | | success, including `--dry-run` |
| 1 | `INTERNAL` | bug in the CLI |
| 2 | `USAGE`, `INPUT_INVALID` | unknown command/flag, schema validation, missing required input |
| 3 | `AUTH_REQUIRED`, `AUTH_EXPIRED` | no credentials, 401 |
| 4 | `FORBIDDEN`, `SCOPE_MISSING` | 403; `SCOPE_MISSING` when the API says the token lacks a scope (S1 sends `error: scope_missing, required: [...]`) |
| 5 | `NOT_FOUND` | 404 |
| 6 | `CONFLICT`, `SYNC_CONFLICT`, `IDEMPOTENCY_MISMATCH` | 409; course-as-code conflicts; key reused with a different body |
| 7 | `VALIDATION_FAILED` | 422 from the API; `details.fields` holds Laravel's `errors` |
| 8 | `RATE_LIMITED` | 429; `details.retryAfter` seconds |
| 9 | `SERVER_ERROR`, `NETWORK` | 5xx, unreachable; `retryable: true` |
| 10 | `TIMEOUT` | `--wait` exceeded `--timeout`; `details.operation` lets the caller resume |
| 11 | `CONFIRMATION_REQUIRED` | destructive command without `--yes` in non-interactive mode |
| 12 | `FEATURE_DISABLED`, `UNSUPPORTED_SERVER` | 503 from `EnsureAiEnabled`, missing module, server older than the CLI needs |
| 13 | `DIFF_FOUND` | `ulams diff --exit-code`, `apply --dry-run --exit-code` with changes |

Each code has a default hint (`src/errors.ts`), overridable per command. Hints name a concrete next
command. Retries: the HTTP client retries `NETWORK`, 502/503/504 and 429 (honouring `Retry-After`) up to 3
times with jittered backoff, **only** for idempotent commands or requests carrying an `Idempotency-Key`.

### 4.7 Dry-run, diff and confirmation

- `kind: read` ignores `--dry-run`.
- `dryRun: "client"` (default for write/destructive): `plan()` (default implementation) fetches the
  current resource with the matching `get` command when one exists, computes a JSON diff between current
  and the fields the request would change, and returns:
  ```json
  { "ok": true, "command": "courses.update", "data": { "dryRun": true,
    "request": { "method": "PATCH", "path": "/api/admin/courses/12", "body": { "title": "New" } },
    "changes": [ { "op": "replace", "path": "/title", "from": "Old", "to": "New" } ] } }
  ```
  Human mode prints a unified, coloured diff. Create shows the payload as all-`add`; delete shows the
  resource and, when cheap to fetch, dependents (`lessons: 4, topics: 19, enrolled users: 37`).
- `dryRun: "server"`: commands whose effect only the server can compute (builder apply, Living Course
  apply, tenant create) call the server's own preview (`GET versions/{v}/diff`, proposal items) and show it.
- Destructive commands: on a TTY without `--yes`, ask "Type the id to confirm"; otherwise exit 11 with
  the plan in `error.details.plan`. `--yes` skips. MCP uses its own flow (8.4).

### 4.8 Pagination and long-running operations

- `paginated` commands: `--page`, `--per-page` (default 25, max what the API allows, usually 100),
  `--all` (follows `meta.last_page`, emits NDJSON items when output is `ndjson`, else one array),
  `--limit n` (stop after n items across pages).
- `longRunning: { start, poll, done }`: `start` returns an operation handle `{kind, id, statusCommand}`;
  `--no-wait` prints it and exits 0; `--wait` (default) polls with backoff 0.5 s → 5 s, prints progress
  to stderr (or NDJSON `progress` lines), returns the final resource, exits 10 on timeout with the
  handle.
- `ulams operations get <handle>` and `ulams operations wait <handle>`: handle syntax `<kind>:<id>`, e.g.
  `builder-run:01J9…`, `living-run:01J…`, `tenant-op:42`. Kinds are registered by the commands that
  produce them.
- Operations in scope: builder runs (generation, patch, apply; S4 endpoint, fallback polling the session
  snapshot as `waitForApplied` does), Living Course check/analyse/apply runs, tenant provisioning (S3),
  course zip import, SCORM parse.

### 4.9 HTTP client

`src/http/client.ts` wraps `createClient` from `@ulams/sdk` (reuse `raw`, `ApiError`, `buildPath`) and adds:

- `call(method, path, {params, query, body, files, stream})` with multipart when `files` is set
  (`FormData` + `Blob` from `ctx.fs.readFile`), so the SDK gains a `form` option (small SDK change in M2,
  with a test; the builder client already does it).
- Headers: `Authorization`, `Accept: application/json`, `User-Agent: ulams-cli/<version> (<os>; node|bun)`,
  `X-Ulams-Client: cli|mcp`, `X-Ulams-Agent: <name>` when `ULAMS_AGENT` or the MCP client name is known
  (stored in the audit log, S1), `X-Request-Id: <ulid>`, `Idempotency-Key` for POST/PATCH.
- Maps `ApiError` to the error codes of 4.6 (`status 0` → `NETWORK`).
- Binary downloads (`output: file`) stream to `--out <path>` (or stdout with `--out -`).
- Timeout default 60 s per request (`--timeout` covers whole `--wait` operations).

### 4.10 Coverage matrix (CI-enforced)

`scripts/coverage.mjs` reads `spec/openapi.json`, the registry (`ulams schema --json` via the built CLI)
and `spec/exclusions.yaml` and writes `coverage/matrix.json` and `coverage/matrix.md`:

| Operation | Command | Status |
|---|---|---|
| `GET /api/admin/courses` | `courses.list` | covered |
| `POST /api/payments-gateways/webhook/stripe` | | excluded: inbound webhook |
| `PUT /api/courses/progress/{topic_id}/ping` | `my.progress-ping` | covered |

- Every operation must be **covered** (some command lists it in `endpoints`) or **excluded** with a reason
  from a fixed list: `inbound-webhook`, `browser-only` (OAuth redirects, LTI launches, social login),
  `content-runtime` (SCORM/cmi5/xAPI runtime called by players), `internal` (health), `deprecated`.
- `ulams api` covers everything as a fallback, but does not count as coverage.
- CI (`.github/workflows/ci.yml` `js` job): `yarn workspace ulams coverage` fails when an operation is
  neither covered nor excluded, or when a command lists an endpoint that is not in the spec. The summary
  line (`312/407 covered, 61 excluded, 34 missing`) goes to the job summary.
- Target: 100 % covered-or-excluded at the end of M2; the matrix is regenerated whenever
  `spec/openapi.json` changes (same PR).

### 4.11 Topics of every type

Topics are created through `POST /api/admin/topics` (multipart, `topicable_type` + `value[...]`) and the
format-specific upload endpoints. Hand-written commands give agents one obvious path per type:

| Command | Does |
|---|---|
| `topics create-richtext --lesson <id> --title … --markdown @lesson.md` | RichText topic |
| `topics create-video --lesson … --file video.mp4` / `--youtube-url …` | video / YouTube |
| `topics create-file --lesson … --file doc.pdf` | PDF / file topic |
| `topics create-scorm --lesson … --package pkg.zip [--sco <id>]` | `scorm.upload` → pick SCO (first if one) → topic with `value[uuid]` |
| `topics create-h5p --lesson … --package x.h5p` | upload to the H5P service (`POST /h5p/contents/upload`, `h5p_file`) → topic with the content id |
| `topics create-liascript --lesson … --markdown @course.md` or `--file x.zip` | `POST /api/admin/liascript` → topic |
| `topics create-cmi5 --lesson … --package au.zip` | `POST /api/admin/cmi5` → topic |
| `topics create-quiz --lesson … --input @quiz.yaml` | GIFT quiz topic + questions (`topic-type-gift`) |
| `topics create-adapt`, `create-project`, `create-layout` | per package, from `--input` |
| `topics resources add <topic> --file …` | `POST /api/admin/topics/{id}/resources` field `resource` |

The implementer reads `api/packages/topic-types` and each package's request class to get the exact
`topicable_type` and `value[...]` fields, and writes one fixture-based unit test per type. Each command
returns the created topic and, for uploads, the processed package id.

---

## 5. Authentication and configuration

### 5.1 Profiles and credentials (M1)

- Config dir: `$ULAMS_CONFIG_DIR`, else `$XDG_CONFIG_HOME/ulams`, else `~/.config/ulams` (macOS and Linux),
  `%APPDATA%\ulams` (Windows). No dependency; 15 lines in `config/paths.ts`.
- `config.json` (0644): `{ "contract": 1, "defaultProfile": "coffee", "profiles": { "coffee": {
  "url": "http://coffee.localhost", "kind": "tenant", "user": "admin@coffee.ulams.app",
  "tokenId": "…", "expiresAt": "…", "scopes": ["*"] } } }`.
- `credentials.json` (**0600**, created with `O_EXCL`, re-chmodded on every write; refuse to read it if
  group/world-readable on POSIX, error `INSECURE_CREDENTIALS` hint `chmod 600 …`): `{ "coffee": { "token":
  "…" } }`. OS keychain storage is a later, opt-in feature (native modules break bun cross-compilation).
- `ULAMS_TOKEN` + `ULAMS_URL` need no files at all (CI, agents, containers).
- A profile is bound to one origin: tokens are per tenant (per-tenant Passport keys). `kind: platform`
  profiles point at a platform host (S3).
- `ulams profiles list|use <name>|delete <name>`; `ulams config get|set <key> <value>` for
  `defaultOutput`, `color`.

### 5.2 Login methods

| Command | Milestone | Server need |
|---|---|---|
| `ulams login --url <origin> --token-stdin` | M1 | none (any valid token; `GET /api/profile/me` checks it) |
| `ulams login --url … --email … --password-stdin` | M1 | existing `POST /api/auth/login` with `remember_me=1` (1-month token). Prints a warning that this token is unscoped and recommends `ulams tokens create` once S1 is live |
| `ulams login --url … --demo admin\|tutor\|student` | M1 | existing `POST /api/demo/login` (demo tenants only) |
| `ulams login --url …` (browser) | S2 | device flow (5.3); default once the server reports `deviceLogin` in `/api/meta` |
| `ulams logout [--revoke]` | M1 / S1 | deletes local credentials; `--revoke` also revokes the token server-side (S1) |
| `ulams whoami` | M1 | `GET /api/profile/me` (+ `GET /api/auth/tokens/current` after S1): user, roles, tenant host, token id, scopes, expiry, auth source (`env`, `profile`) |

`--password-stdin` and `--token-stdin` read one line from stdin; interactive mode may prompt with echo off.

### 5.3 Device login (S2; ADR 0075; default, pending #74)

Our own flow with the RFC 8628 wire format; Passport's device grant stays disabled.

1. `POST /api/auth/device/code` (no auth, throttled 10/min/IP) body
   `{ "client_name": "ulams-cli on mateusz-mbp", "scopes": ["courses:write", "builder:write"], "agent": null }`
   → `{ device_code, user_code: "BDWP-HQPK", verification_uri, verification_uri_complete, expires_in: 600, interval: 5 }`.
   `user_code`: 8 characters from `BCDFGHJKLMNPQRSTVWXZ`, shown as `XXXX-XXXX`. `verification_uri` =
   `{web_url}/cli/authorize` from the tenant's frontend URL setting.
2. CLI prints the code and URL on stderr, opens the browser if a TTY is present (`open`/`xdg-open`/`start`
   via `child_process`, no dependency), polls `POST /api/auth/device/token {device_code}` every
   `interval` s. Errors exactly as RFC 8628: HTTP 400 `{ "error": "authorization_pending" | "slow_down" |
   "access_denied" | "expired_token" }`.
3. The user opens `/cli/authorize` in the web app (logged in through the existing BFF), sees the client
   name, IP, requested scopes, can untick scopes and choose an expiry (7, 30, 90 days; default 90; max
   365; platform tokens max 30), then approves or denies. The page calls
   `GET /api/auth/device/requests/{user_code}` and `POST …/approve {scopes, expires_in_days}` /
   `POST …/deny` with the user's token (throttled 5/min/user).
4. On approval the server mints a **scoped personal access token** (S1) owned by that user, effective
   scopes = approved ∩ requested; the next poll returns `{ access_token, token_type: "Bearer", expires_at,
   scopes, token_id }` once (the row stores only a hash afterwards).
5. Table `device_authorizations` (tenant DB): `id`, `device_code_hash`, `user_code_hash`, `client_name`,
   `requested_scopes` json, `approved_scopes` json, `status` (pending, approved, denied, consumed,
   expired), `user_id` null, `token_id` null, `ip`, `user_agent`, `last_polled_at`, `expires_at`,
   timestamps. Pruned daily (expired > 1 day).
6. Package: `api/packages/auth` (new `DeviceAuthorizationService`, controller with Swagger, requests with
   policies, migration, tests incl. tenant isolation: a code created on `coffee` cannot be approved on
   `oncall`).
7. Web app: page `front/web/src/pages/cli/authorize.astro` (+ BFF proxy routes), WCAG 2.2 AA (axe in the
   existing e2e), docs page.

### 5.4 Redaction

`config/redact.ts` is applied to every string that reaches stderr, `--debug` logs, error `details` and MCP
results: header values for `Authorization`, `Cookie`, `Set-Cookie`, `Idempotency-Key`; JSON keys matching
`/token|secret|password|passwd|api[_-]?key|private[_-]?key|device_code|client_secret/i`; bearer-looking
strings (`eyJ[\w-]+\.[\w-]+\.[\w-]+`, `ulams_pat_\w+`). Replacement: `«redacted»`. Tokens are printed
**once**, by `tokens create` (data field `token`), and never by `whoami`/`profiles`. Unit test: every error
path with a token in the body prints no token.

---

## 6. Server-side work

### 6.1 S1 scoped personal access tokens (ADR 0074)

- Built on Passport personal access tokens (`$user->createToken($name, $scopes)`), so `auth:api` keeps
  working. New table `api_token_meta` (tenant DB): `token_id` (FK `oauth_access_tokens.id`, unique),
  `kind` (`cli`, `agent`, `ci`, `integration`), `agent_name` null, `created_via` (`admin`, `cli`,
  `device`), `rate_limit_per_minute` null, `last_used_at`, `last_used_ip`. Tokens without a meta row
  (login, LTI, demo) are **unscoped** and behave as today.
- Expiry: the service sets `Passport::personalAccessTokensExpireIn()` per call (the same static API
  `AuthService` uses) and restores the previous value in `finally`. Max 365 days; platform tokens 30.
- Token string returned once, prefixed `ulams_pat_` so secret scanners can match it; a global middleware
  `StripTokenPrefix` (prepended before auth) removes the prefix from `Authorization` before Passport sees
  it. Ask GitHub secret scanning partnership later (not in scope).
- Endpoints (package `auth`, Swagger attributes or docblocks, policies, tenant isolation tests):
  - `GET /api/auth/tokens` (own), `POST /api/auth/tokens {name, scopes[], expires_in_days, kind, agent_name}`,
    `DELETE /api/auth/tokens/{id}`, `GET /api/auth/tokens/current`.
  - `GET /api/admin/tokens` (all users; permission `token_manage`), `DELETE /api/admin/tokens/{id}`.
  - `GET /api/admin/tokens/{id}/audit`, `GET /api/admin/agent-audit?token_id=&user_id=&from=&to=`.
- Admin page "API tokens" (`admin/src/pages/Tokens`) list, revoke, audit view; profile section "My tokens"
  in the web app. Docs page for both (coverage check needs the admin route).
- CLI: `ulams tokens list|create|revoke|current|audit`.

### 6.2 Scopes and enforcement

Scopes are `<area>:read` and `<area>:write` (write implies read), plus `*`:

| Area | Covers (route prefixes) |
|---|---|
| `courses` | admin courses, lessons, topics, topic resources, categories, tags, files, scorm, cmi5, liascript, h5p, adapt, gift quizzes/questions, project, images, video, youtube, course import/export, dictionaries |
| `users` | admin users, user-groups, roles, permissions, csv-users, assign-without-account |
| `enrolments` | course access, access enquiries, consultation access |
| `settings` | settings, config, pages, templates (+ email/sms/pdf), translations, notifications admin, model-fields, mattermost/mailerlite admin |
| `events` | webinars, stationary events, consultations, jitsi, pencil-spaces |
| `certificates` | `admin/pdfs`, certificate templates |
| `commerce` | orders, products, vouchers, payments, invoices (admin) |
| `reports` | reports, stats, questionnaire reports, tasks admin |
| `lti` | `admin/lti` |
| `builder` | `admin/course-builder` |
| `living-course` | `admin/living-course` (when merged) |
| `learner` | profile, my courses, progress, quiz attempts, bookmarks, notes, notifications, cart, own orders |
| `tokens` | `auth/tokens` |
| `platform` | platform API (S3), platform host only |

- Map file: `api/packages/auth/resources/token-scopes.php` → array of `[pattern, area]` matched against the
  route URI (`api/admin/courses*`), first match wins. Exported as JSON for the CLI generator by
  `php artisan ulams:tokens:export-scopes` (writes `api/packages/auth/resources/token-scopes.json`,
  committed).
- Middleware `EnforceTokenScopes` (appended to the `api` group): if the request's token has an
  `api_token_meta` row, require `area:read` for GET/HEAD and `area:write` otherwise; `*` passes. Unmapped
  route + scoped token → **deny** (fail closed). Response 403
  `{ "success": false, "message": "This token lacks the courses:write scope.", "error": "scope_missing", "required": ["courses:write"] }`.
- Effective permission = the user's Spatie permissions **and** the scope (scopes only narrow).
- Presets (CLI and approval page): `read-only` (all `:read` except `platform`), `author` (`courses`,
  `builder`, `living-course` write; `reports:read`), `admin` (`*` on a tenant host), `learner`, `ci`
  (`courses:write`, `builder:write`, `living-course:write`).
- Test `TokenScopeMapCoverageTest`: every route with `auth:api` matches a pattern. Feature tests per area
  (read allowed, write denied, unmapped denied, unscoped login token unaffected).

### 6.3 Agent audit log

Table `agent_audit_log` (tenant DB, append-only): `id`, `token_id`, `user_id`, `agent_name`, `client`
(`cli`/`mcp`/other from `X-Ulams-Client`), `user_agent`, `method`, `route_name`, `path`, `route_params`
json, `status`, `dry_run` bool, `idempotency_key`, `request_id`, `duration_ms`, `ip`, `created_at`.
Middleware `RecordAgentAudit` (terminable) writes one row for every **non-GET** request authenticated with a
scoped token, and for GET requests on `users`/`reports` areas (data access). No bodies (privacy). Retention
setting `ulams_auth.agent_audit_days` (default 365). Indexed by `(token_id, created_at)`.

### 6.4 `Idempotency-Key` and `X-Request-Id`

- Middleware `Idempotency` in `core` for POST/PATCH/PUT/DELETE with the header: key scope
  `(tenant, user_id, method, route, key)`; store `{body_hash, status, response_body, headers subset}` in the
  cache for 24 h; same key + same body hash → replay the stored response with header
  `Idempotent-Replayed: true`; same key + different body → 422 `{error: "idempotency_mismatch"}`;
  concurrent duplicate → 409 `{error: "idempotency_in_progress"}` (cache lock, 30 s). Key max 255 chars.
- Middleware `RequestId`: accept `X-Request-Id` (ULID/UUID, else generate), add it to the log context and
  echo it in the response.

### 6.5 Capabilities endpoint

`GET /api/meta` (no auth): `{ "api": "ulams", "version": "<app version>", "contract": 1, "host": "coffee.localhost",
"kind": "tenant|platform", "features": { "ai": true, "courseBuilder": true, "livingCourse": false,
"deviceLogin": true, "scopedTokens": true, "idempotency": true, "platformApi": false, "demo": true } }`.
The CLI calls it on `login` and caches it in the profile for 1 hour; commands that need a missing feature
exit 12 `FEATURE_DISABLED` before calling the API.

### 6.6 S3 platform tenant API (ADR 0078; default, pending #79)

- Routes on platform hosts only (`TENANCY_PLATFORM_HOSTS`), disabled unless `TENANCY_PLATFORM_API=true`
  (404 otherwise and on tenant hosts): `GET /api/platform/tenants`, `GET /api/platform/tenants/{slug}`,
  `POST /api/platform/tenants {slug, name, theme, accent, users, demo}` → 202 `{operation}`,
  `PATCH /api/platform/tenants/{slug}/env {set: {}, unset: []}` (same allow-list as `set-env`),
  `DELETE /api/platform/tenants/{slug} {confirm: "<slug>"}` → 202 `{operation}`,
  `GET /api/platform/operations/{id}` → `{status: queued|running|succeeded|failed, steps: [...], error}`.
- Provisioning moves into a queued job `ProvisionTenantJob` that runs the same `TenantProvisioner` steps
  (database, bucket, env, migrate, passport_keys, passport_client, permissions, lti_keys, demo) and records
  each in `tenant_operations` (platform DB). The artisan command keeps working (runs the job inline).
- Auth: platform users with the `platform_admin` permission, tokens with scope `platform:write`/`read`.
- CLI: `ulams tenants list|get|create|delete|set-env`, `create` and `delete` long-running.
- Tests: platform-only routing, disabled flag, delete confirmation, job steps, failure surfaced in the
  operation.

### 6.7 OpenAPI completeness (S5)

- **L0-11** in `docs/plans/leftovers-0-2.md` (ADR 0047) converts docblocks to attributes and adds the
  unscanned packages (course-builder included). The CLI does not wait for it: `sync-spec` works with the
  docblock spec today, and builder commands are hand-written.
- Response schemas: after L0-11, add `#[OA\Response(... content: new OA\JsonContent(ref: ...))]` with
  envelope schemas for the **top 60 operations** the nouns use (list in `front/cli/coverage/matrix.md`,
  column "untyped", sorted by noun). Pattern: one reusable `Envelope` schema with `allOf` + the resource
  schema already defined in the package's `Swagger/` folder. Each batch regenerates `spec/openapi.json`,
  `front/sdk/src/generated/openapi.ts` and the CLI commands in the same PR.
- Add stable `operationId`s while converting (`courses.list` style, matching the CLI id) so the generator
  can prefer them over path-derived names. Not required for M2.
- New endpoints from S1–S4 get attributes (if L0-11 has landed) or docblocks.

### 6.8 S4 run status

`GET /api/admin/course-builder/runs/{run}` → `{ id, sessionId, kind, status (queued|running|succeeded|failed|cancelled),
steps: [{id, name, status, error}], startedAt, finishedAt, error }`. Policy: session owner or
`course_builder_manage`. Tenant isolation test. Living Course runs reuse the same run model (Phase 3), so
the endpoint covers them after the merge.

---

## 7. Command catalogue (what each milestone delivers)

### 7.1 M1 core commands

`login`, `logout`, `whoami`, `profiles list|use|delete`, `config get|set`, `api`, `schema`, `describe`,
`version`, `completion bash|zsh|fish`, `help`.

`ulams api <METHOD> <path> [--param k=v]* [--query k=v]* [--body @file|-|json] [--form field=@file]*
[--raw]`:
- Path may use spec templates (`/api/admin/courses/{id}` with `--param id=12`) or literal paths.
- Without `--raw`, output is the standard envelope with `data` = the API's `data`; with `--raw` the whole
  response body.
- `ulams api --list [--filter courses]` prints the operations from the bundled spec (method, path,
  summary, command id if covered), so agents can find endpoints that have no noun yet.
- `--dry-run` prints the request it would send. Methods other than GET count as `write` (DELETE as
  destructive) for confirmation and MCP annotations.

`ulams schema [--command <id>] [--format json]`: the registry as JSON (4.2), plus `globals`, the envelope
schema, the exit-code table and `contract`. `ulams describe <noun> <verb>` (or `<id>`): one command with
flags, JSON Schema, examples, scopes, kind. Both work without credentials.

### 7.2 M2 nouns (generated + hand-written)

| Noun | Verbs (beyond list/get/create/update/delete) | Notes |
|---|---|---|
| `courses` | `publish`, `unpublish`, `clone`, `export`, `import`, `program`, `authors-*` | publish = update `status`; export/import zip with `--out`/file |
| `lessons` | `reorder`, `clone` | `--course` required on list/create |
| `topics` | `create-<type>` (4.11), `reorder`, `resources add|delete`, `clone` | |
| `quizzes`, `questions` | `questions import-gift --file` | GIFT |
| `categories`, `tags`, `files` | `files upload --target dir file…`, `files move` | |
| `scorm`, `h5p`, `liascript`, `cmi5` | `upload`, `list`, `delete` | low-level; `topics create-*` is the usual path |
| `users` | `settings-get|set`, `interests-*`, `avatar-set`, `import-csv` | |
| `groups` | `add-member`, `remove-member`, `tree` | |
| `roles`, `permissions` | `grant`, `revoke` | |
| `access` | `list --course`, `grant --course --user\|--group`, `revoke`, `set` (destructive), `enquiries list|approve|delete` | "enrol" alias: `ulams enrol --course 12 --user 34` |
| `settings` | `groups`, `get <group> <key>`, `set <group> <key> <value>` | |
| `theme` | `get`, `set --theme --accent` | settings group `theme` keys `theme`, `accent` |
| `pages`, `templates`, `notifications`, `translations` | `templates preview`, `notifications send` (bulk) | |
| `webinars`, `events`, `consultations` | `consultations slots`, `webinars generate-jitsi` | |
| `certificates` | `templates list|get|create|update|delete`, `preview`, `generate --id --out` | `templates-pdf` |
| `orders`, `products` | read only (`list`, `get`) | commerce stays read-only; Sylius later |
| `reports`, `stats` | `reports metrics|report`, `stats course|topic|cart|date-range`, `stats course-export --out` | |
| `lti` | `registrations list|create|update|delete`, `deployments *`, `tools *` | per `api/packages/lti` routes |
| `questionnaires`, `tasks` | generated | |
| `my` | `courses`, `progress`, `progress-set`, `quiz start|answer|end`, `notifications`, `profile get|update` | learner audience |
| `tokens` | (S1) | |
| `apply`, `get -o yaml` | (7.3) | |
| `operations` | `get`, `wait` | |

### 7.3 Declarative `apply -f` (M2, last slice)

Manifests, YAML or JSON, one or many documents:

```yaml
apiVersion: ulams.dev/v1
kind: Course              # Course, Lesson, Topic, User, Group, Role, Setting, Page, Template, Access
metadata:
  key: kubernetes-101     # natural key, see table
spec:
  title: Kubernetes 101
  status: draft
  lessons:                # optional nested children for Course
    - key: install
      title: Install
      topics:
        - { key: intro, type: richtext, title: Intro, markdown: "@lessons/intro.md" }
```

| Kind | Natural key → lookup |
|---|---|
| Course | `slug` if the API exposes it, else `title` exact match (error on duplicates; suggest adding `metadata.id`) |
| Lesson / Topic | parent + `title`, or `metadata.id` |
| User | `email` |
| Group, Role, Page, Template | `name` / `slug` |
| Setting | `group` + `key` |
| Access | course + user/group (set semantics) |

Algorithm: parse → validate against per-kind zod → resolve each object (GET/list) → compute diff → print
plan (`--dry-run` stops here, `--exit-code` exits 13 on changes) → apply creates/updates in dependency
order with `Idempotency-Key = sha256(profile url + kind + key + spec hash)` → result list
`[{kind, key, id, action: created|updated|unchanged|failed, error?}]`. Re-running with no changes performs
zero writes. `--prune` (destructive, needs `--yes`) deletes children not in the manifest.
`ulams get <kind> <key> -o yaml` emits a manifest from the live resource (round-trip test).

### 7.4 M4 builder commands (hand-written on `createCourseBuilderClient`)

| Command | Maps to |
|---|---|
| `builder sessions list|get|create|delete` | sessions |
| `builder create --from <file>… [--title] [--answers answers.yaml] [--approve-outline] [--apply] [--publish] --wait` | composite: create → upload sources → stream until a question or outline surface → answer from file or exit with the pending question (exit 0, `data.pending`) |
| `builder sources add <session> <file>` / `sources get` / `fragments get <id>` | sources |
| `builder brief get|set <session> --input @brief.yaml` | brief |
| `builder interview show <session>` | reads the current A2UI interview surface from `GET sessions/{s}` and returns `{questionId, prompt, kind, options[]}` |
| `builder interview answer <session> --question <id> --value …` / `decide <session>` | `runs` action `answer` / `decide_for_me` (surfaceId and sourceComponentId resolved from `show`) |
| `builder outline approve <version> [--edit <objectiveId>=<text>]…` / `reject --comment` | `versions/{v}/approve|reject` |
| `builder chat <session> --element <elementId> "<instruction>"` | `runs.message` with selection |
| `builder patches approve|reject <version>` | approve/reject versions of kind patch |
| `builder versions list|get|diff|restore`, `builder undo|redo <session>` | versions |
| `builder apply <session> --wait` / `builder publish <session>` | apply (long-running, S4) / publish |
| `builder runs get|cancel|retry-step` | runs (S4) |
| `builder usage <session>` | usage (model, tokens, cost) |
| `builder events <session> [--follow] [--after <id>] [--types RUN_FINISHED,…] [--until-run <runId>]` | SSE → NDJSON (below) |

Event streaming: reuse `connectEventStream` from `@ulams/sdk/ag-ui`; reconnect on the server's 25 s close
with `Last-Event-ID`; emit each AG-UI event as `{"type":"event","data":<event>}`; `--until-run` stops after
that run's `RUN_FINISHED`/`RUN_ERROR` (exit 9 on `RUN_ERROR` with the event in `details`). Without
`--follow` it prints stored events up to now and exits.

### 7.5 M4b Living Course commands (after Phase 3 merges)

`living sources list <session>`, `living revisions list|get|changes|upload`, `living connectors list`,
`living connect <session> --connector git --repo … --path … --branch …`, `living connections update|delete|check|webhook-secret`,
`living proposals list|get|analyse|reanalyse`, `living proposals accept|reject|reset|regenerate <proposal> --item <id>`,
`living proposals accept-all|reject|apply <proposal> --wait`, `living proposals learner-note`,
`living staleness <session>`, `living audit list|export|verify`. Write them against the routes on
`phase-3/living-course` once it is on `main`; `apply` returns 409 conflicts as `CONFLICT` with the
conflicting items in `details`.

### 7.6 Tenants (S3)

`tenants list|get|create|delete|set-env` on a `kind: platform` profile; `create --wait` streams the step
list.

### 7.7 Commands not covered by the API today

Found while planning; each becomes a `(new)` TODO item and is added when needed:
- Run status (S4), capabilities (6.5), token management (S1), device flow (S2), tenants (S3).
- `GET /api/admin/courses/{id}/blueprint` and `POST /api/admin/course-builder/sessions/{s}/blueprint`
  (import a hand-edited blueprint as a new version, no LLM): needed by M5.
- Learner "next lesson" helper for 7.5's learner MCP: later.

### 7.8 Interactivity rules for agents

No command asks a question in non-interactive mode. Commands that in the UI involve a choice (pick a SCO,
choose a template) take it as a flag; when absent and the choice is ambiguous, they exit 2 with
`INPUT_INVALID` and `details.choices` listing the options, so an agent can re-run with one.

### 7.9 Webhooks (placeholder)

When 7.3 webhooks exist: `ulams webhooks endpoints list|create|delete|test`, `webhooks deliveries list|replay`,
and `ulams webhooks listen --forward-to http://localhost:3000/hook` (like `stripe listen`). Not designed here.

---

## 8. MCP server (`ulams mcp`, M3; ADR 0076; default, pending #75)

### 8.1 Protocol and SDK

- Spec **2026-07-28** via the TypeScript SDK v2: `@modelcontextprotocol/server` (+ `@modelcontextprotocol/node`
  for HTTP). Licence: existing code MIT, new contributions Apache-2.0 (compatible with our MIT front; no
  NOTICE obligations beyond keeping the licence text, which tsup preserves in `dist/THIRD_PARTY_LICENSES`).
  v2 is the line the SDK maintainers recommend; v1 stays only as a fallback if a v2 blocker appears.
- Stateless: no protocol sessions. Cross-call state (builder sessions, confirm tokens) is passed as tool
  arguments.
- Transports:
  - `ulams mcp` (default **stdio**): logs to stderr only; stdout is protocol.
  - `ulams mcp --http [--host 127.0.0.1] [--port 8787]`: Streamable HTTP at `/mcp`; binds to loopback by
    default; `--host 0.0.0.0` requires `--allow-remote` and prints a warning.

### 8.2 Auth

- stdio: the profile/env credentials of the user who starts the server (`--profile`, `ULAMS_TOKEN`). The
  MCP client config holds no secret when a profile is used.
- HTTP: each request must carry `Authorization: Bearer <ulams token>`; the server forwards it to the API
  per request (no token store). The `--url` (tenant) is fixed at start. Missing/invalid token → HTTP 401
  with `WWW-Authenticate: Bearer`. Full MCP OAuth (protected resource metadata, Client ID Metadata
  Documents) belongs to the hosted variant, not this milestone.
- `X-Ulams-Client: mcp` and `X-Ulams-Agent: <clientInfo.name>` go to the API, so the agent audit log
  (6.3) shows which agent acted.

### 8.3 Tools

- One tool per registry command with `mcp.expose !== false`. Not exposed: `login`, `logout`, `profiles`,
  `config`, `mcp`, `completion`, `version`; `local` commands (course-as-code file operations) only on stdio.
- Name `courses_list` (`id` with `.` → `_`), `title` from summary, `description` = summary + description +
  one example, `inputSchema` = JSON Schema of `input` (globals that make sense are added: `dry_run`,
  `page`, `per_page`, `all`, `fields`, `idempotency_key`, `confirm`), `outputSchema` when the output is
  typed, deterministic order (spec recommendation).
- Annotations: `readOnlyHint = kind === "read"`, `destructiveHint = kind === "destructive"`,
  `idempotentHint = idempotent`, `openWorldHint = false`.
- Results: `structuredContent` = the CLI envelope (`ok`, `data`, `meta`, `warnings` or `error`),
  `content` = one text block with compact JSON (≤ 25 kB; longer lists are truncated with
  `warnings: [{code: "TRUNCATED", hint: "use page/per_page or fields"}]`). Errors set `isError: true` with
  the same error object (code, message, hint).
- **Toolsets** keep the tool list small for models: `--toolsets core,courses,builder,users,…` (default
  `core`). `core` = `whoami`, `courses_list|get|create|update|publish`, `lessons_*`, `topics_create_richtext`,
  `topics_create_quiz`, `users_list|get|create`, `access_grant`, `reports_metrics`, plus three meta tools:
  - `commands_search {query}` → matching command ids with summaries (all toolsets);
  - `commands_describe {id}` → the command's JSON Schema and examples;
  - `commands_run {id, input}` → runs any exposed command (same confirmation and annotations checks).
  `--toolsets all` exposes everything (~300 tools; for clients that filter tools themselves).
- Modes: `--read-only` exposes only `read` commands (and `commands_run` refuses others);
  `--no-destructive` hides destructive commands.

### 8.4 Confirmation of destructive tools

1. A destructive tool called without `confirm` runs its `plan()` and returns
   `{ ok: false, error: { code: "CONFIRMATION_REQUIRED", hint: "Show the plan to the user, then call again with confirm", details: { plan, confirm: "<token>" } } }`
   with `isError: true`. `<token>` = HMAC-SHA256 (per-process random key) over `id + canonical input + expiry`,
   valid 5 minutes, single use.
2. If the client declares elicitation support, the server instead returns an `input_required` result
   (Multi Round-Trip Request) with a form elicitation "Delete course 12 'Kubernetes 101' with 4 lessons and
   37 enrolments?" (accept/decline), and runs on the retry when accepted.
3. `--yes` on `ulams mcp` disables confirmation (documented as unsafe; for sandboxes only).
4. `dry_run: true` works on every write tool and never needs confirmation.

### 8.5 Resources

- Templates: `ulams://courses/{id}` (Markdown: title, status, lessons and topics outline),
  `ulams://courses/{id}/program.json`, `ulams://builder/sessions/{id}` (state summary),
  `ulams://builder/versions/{id}/blueprint.json`.
- `resources/list` returns the first 100 courses (`ttlMs: 60000`, `cacheScope: "private"`).
- A2UI over MCP (7.5) is later; lesson resources are Markdown now.

### 8.6 Tool descriptions for models

Every hand-written command and every override `description` follows: first sentence = what it does; then
when to use it versus the nearest alternative; required ids and where to get them ("course ids come from
`courses_list`"); side effects; one example input. The eval (12.5) measures them.

---

## 9. Course-as-code (M5, after Phase 3; ADR 0079)

### 9.1 Layout

```
kubernetes-101/
  ulams.yaml                 # contract, course key, default profile name (no secrets)
  course.yaml                # course: title, subtitle, description, language, audience, objectives, seo, faq, pages
  modules/
    01-basics/
      module.yaml            # id, title, summary
      01-install.md          # lesson
      01-install.quiz.yaml   # lesson quiz (optional)
      02-pods.md
  final-test.quiz.yaml       # optional
  assets/                    # images, SCORM/H5P/LiaScript packages referenced by topics
  sources/                   # optional source documents (Living Course inputs)
  .ulams/state.json          # sync base, committed (see 9.3)
```

Lesson file:

```markdown
---
id: 01j9z8x7w6v5t4s3r2q1p0n9m8      # ULID; added by `ulams validate --fix` if missing
title: Install kubectl
summary: Install and verify kubectl.
minutes: 15
objectives: [obj-install]
---

:::paragraph{#01j9z8… cite="frg_abcdefabcdef"}
kubectl is the command-line tool for Kubernetes clusters.
:::

:::code{#01j9z9… origin=author}
```bash
kubectl version --client
```
:::
```

- Container directives (`:::kind{#id cite="frg_…,frg_…" origin=ai|author}`) map 1:1 to Blueprint blocks;
  plain Markdown outside directives becomes `paragraph` blocks on `validate --fix`.
- Quiz YAML maps to the Blueprint `quiz` (`type`, `prompt`, `options`, `explanation`, `citations`).
- Non-RichText topics (SCORM, H5P, LiaScript, video, file) need **Blueprint v2** (`contentType` enum +
  `asset` reference); M5 includes the v2 schema (`course-blueprint/v2.json`, v1 still accepted).
- Citations: blocks with `origin: author` may omit citations; AI-originated blocks must cite (default,
  pending #78). `validate` reports uncited author blocks as warnings.

### 9.2 Commands

| Command | Does |
|---|---|
| `ulams init [--from-course <id>]` | scaffold a folder (or pull an existing course) |
| `ulams validate [--fix]` | parse, map to a Blueprint, validate with the JSON Schema (bundled copy of `v1.json`/`v2.json`) and the semantic checks ported from `Checks.php`; exit 7 with file:line issues |
| `ulams preview` | push to a **draft** builder session version (no apply) and print the studio preview URL |
| `ulams push [--message]` | upload as a new blueprint version (server endpoint 7.7), show the diff against the applied version, apply on `--apply`; Living Course proposal flow when the course has pending proposals |
| `ulams pull` | fetch the applied blueprint and write files; three-way merge with `.ulams/state.json` |
| `ulams diff [--exit-code]` | local vs remote (element-aware diff from `versions/{v}/diff` model) |
| `ulams publish` | publish the course |

### 9.3 Two-way sync

`.ulams/state.json` holds `{remoteCourseId, sessionId, baseVersionId, elements: {<elementId>: <sha256 of normalised element>}}`.
`pull`/`push` compare base, local and remote per element id: changed on one side → take it; changed on
both → conflict, written as a diff file `.ulams/conflicts/<elementId>.diff` plus a `SYNC_CONFLICT` error
(exit 6) listing them; resolution = edit the file, `ulams resolve <elementId> --take local|remote|file`.
Never overwrite silently. Details (rename handling, moved lessons) are designed in the M5 plan section after
Phase 3 merges.

---

## 10. Documentation

- **Generated CLI reference**: `front/docs-site/scripts/generators/70-cli.mjs` runs
  `node front/cli/dist/ulams.mjs schema --json` (built in the docs job) and writes one page per noun to
  `src/content/docs/reference/cli/<noun>.md` (gitignored, `generatedFrom: front/cli`): synopsis, flags
  table, JSON Schema, examples, exit codes, scopes, MCP tool name and annotations. Plus
  `reference/cli/index.md` (global flags, envelope, exit codes) and `reference/cli/coverage.md` from
  `coverage/matrix.md`.
- **Written pages** (count for the coverage check, `modules: [cli]`): `developers/cli.mdx` (install, login,
  profiles, output contract, dry-run, apply, examples), `developers/agents.mdx` (agent guide: Claude Code
  `claude mcp add ulams -- npx ulams mcp --profile coffee`, Cursor/other clients JSON config, toolsets,
  read-only mode, token presets, the "use `--json` and `--input @file`" rules, `commands_search` workflow,
  safety), `developers/api-tokens.mdx` (scopes, presets, audit), `operators/platform-api.mdx` (S3),
  examples in `front/docs-site/examples/cli/*.sh` (create course + lessons + quiz + enrol; bulk users from
  CSV; builder from a Markdown file).
- `AGENTS.md` gets a short "Operating a ulams instance" section pointing agents at `ulams schema` and the
  agent guide.
- Each milestone updates the pages it changes (docs rule).

---

## 11. Distribution (M6; ADR 0077)

| Channel | How | Pending |
|---|---|---|
| npm `ulams` | `tsup` bundles `src/bin.ts` + `@ulams/sdk` → `dist/ulams.mjs` (ESM, Node ≥ 22.12, shebang); runtime deps kept external: `zod`, `yaml`, `@modelcontextprotocol/server`, `@modelcontextprotocol/node`. Published from the release workflow with npm provenance. `npx ulams` works | #77 (name, org, `NPM_TOKEN`) |
| Binaries | `bun build --compile --minify --target=bun-linux-x64,bun-linux-arm64,bun-darwin-arm64,bun-darwin-x64,bun-windows-x64` from one Ubuntu runner; `SHA256SUMS` + Sigstore (cosign keyless) signatures on the GitHub release; smoke test each Linux binary (`--version`, `schema --json`, `api GET /api/meta` against a mock) | #76 (macOS/Windows signing) |
| Docker | `ghcr.io/ulams-dev/ulams-cli:<version>` from `node:22-alpine`, non-root user, `ENTRYPOINT ["ulams"]`, signed like the other images; base for the later 7.1 GitHub Action | |
| Tag | `cli-v<semver>`; CLI versioned independently of the API; `UNSUPPORTED_SERVER` when `/api/meta.contract` is newer/older than supported | |

Why bun compile and not Node SEA: Node SEA is still "active development" (Stability 1.1), cannot
cross-compile (one runner per OS), and ESM entry points need Node 26. Bun compiles every target from one
runner. Risk: runtime differences between Node and bun; mitigated by running the unit suite under both
(`bun test` is not used; `vitest` under Node, plus binary smoke tests) and by the fetch-only runtime rule.

**Telemetry: none.** No analytics, no crash reporting, no update pings. `ulams version --check` queries
the npm registry only when asked.

---

## 12. Testing

### 12.1 Unit (per command, mocked HTTP)

- Vitest in `front/cli/tests/unit`. A `mockApi()` helper builds a `fetch` stub from route → response
  fixtures (like `front/sdk/tests/client.test.ts`). Each hand-written command: success output (envelope
  snapshot), each mapped error (401 → exit 3, 403 scope → 4, 404 → 5, 422 → 7 with field details, 429 →
  retry then 8), `--dry-run` plan, `--json` vs human output, redaction.
- Parser: flag mapping, `--input` merge precedence, positionals, unknown flags → exit 2.
- Generator: golden test on a small fixture spec → expected `commands.ts`.
- Contract tests: JSON Schema of the envelope validates every snapshot; `ulams schema` output snapshot
  (diff reviewed on every change; a removed field without a `contract` bump fails).

### 12.2 Contract against OpenAPI

`tests/contract/spec.test.ts`: every command's `endpoints` exist in `spec/openapi.json`; every generated
command's required params match the spec; the coverage script passes. Runs in CI.

### 12.3 E2E against the local stack (coffee tenant)

`tests/e2e/*.test.ts`, opt-in (`ULAMS_E2E=1`), run locally and in the nightly workflow
(`nightly-conformance.yml`) where the stack runs:
login demo admin → create course → 2 lessons → richtext topic → quiz topic → publish → create student →
grant access → `my progress` as the student → delete course (with `--yes`). Also `apply -f` twice (second
run: all `unchanged`), SCORM upload with `api/packages/scorm/tests` fixture, builder flow with the mocked
LLM provider the course-builder tests use.

### 12.4 MCP

`tests/mcp/*.test.ts` with `@modelcontextprotocol/client` (in-process transport and stdio spawn of the
built CLI): `server/discover`; `tools/list` snapshot per toolset (names, annotations, schemas); a read tool;
a write tool with `dry_run`; a destructive tool returns `CONFIRMATION_REQUIRED` then succeeds with the
confirm token; token reuse fails; `--read-only` hides writes; HTTP mode rejects missing bearer. Manual
check with the MCP Inspector (`npx @modelcontextprotocol/inspector`, not a dependency).

### 12.5 Eval with a real model

`front/cli/evals/` (excluded from CI, run by `yarn workspace ulams eval`):
- Harness: the Claude Agent SDK (or the API with tool use) gets **only** a shell restricted to `ulams …`
  (CLI mode) or only the `ulams mcp` tools (MCP mode), a profile on a fresh demo tenant, and a task.
- Tasks (YAML, with checks run through the API afterwards): create a course with two lessons and a quiz,
  publish it, enrol `student1`; bulk-create 5 users from a CSV and put them in group "Sales"; find the
  course with the lowest completion and report its title; build a course from `fixtures/guide.md` with the
  builder answering the interview with defaults; delete a draft course (must ask for confirmation in MCP
  mode).
- Metrics per task: success (checks pass), number of tool calls, errors hit, tokens and cost (logged as
  for every LLM call), wall time. Budget: max 30 tool calls and 1 USD per task; whole suite ≤ 5 USD. Model
  from config (`ULAMS_EVAL_MODEL`), not hard-coded. Report written to `front/cli/evals/reports/<date>.md`.
- Threshold for M3 DoD: ≥ 4/5 tasks pass in both modes.

### 12.6 Server tests

PHPUnit in the owning packages: token CRUD, scope enforcement (per area, fail-closed), presets, prefix
stripping, audit rows, idempotency replay/mismatch/in-progress, device flow (pending/slow_down/denied/
expired/consumed, brute-force throttle), run status, platform API (platform-only, disabled flag, confirm),
**tenant isolation** for every new endpoint (pattern `api/packages/example-plugin/tests/Api/TenantIsolationTest.php`).

---

## 13. Commit order (per milestone)

Branches: `phase-7/cli-core`, `phase-7/cli-nouns`, `phase-7/cli-mcp`, `phase-7/cli-builder`,
`phase-7/cli-living`, `phase-7/course-as-code`, `phase-7/cli-release`; server: `phase-7/scoped-tokens`,
`phase-7/device-login`, `phase-7/platform-api`, `phase-7/builder-run-status`.

**M1 `phase-7/cli-core`**
1. `chore(cli): add the front/cli workspace` (package.json, tsconfig, eslint, vitest, tsup, root workspace + turbo + CI filter, docs coverage `APPS` entry)
2. `feat(cli): command registry, parser and help` (+ tests)
3. `feat(cli): output envelope, error codes and exit codes` (+ envelope JSON Schema, tests)
4. `feat(cli): profiles, credentials file and redaction` (+ tests incl. file modes)
5. `feat(cli): HTTP client on @ulams/sdk with request ids and retries`
6. `feat(cli): login with token, password or demo; logout; whoami`
7. `feat(cli): ulams api passthrough and api --list from the bundled spec` (+ `sync-spec` script, `spec/openapi.json`)
8. `feat(cli): schema and describe`
9. `docs(cli): CLI page and reference generator` (+ `70-cli.mjs`)

DoD M1: `corepack yarn turbo run typecheck lint test build --filter=ulams` green; demo command in section 3
works against the local stack; `ulams schema --json` validates against its own schema.

**M2 `phase-7/cli-nouns`**
1. `feat(sdk): multipart form bodies in createClient` (+ test)
2. `feat(cli): generate noun commands from OpenAPI` (generator + overrides + generated file + golden test)
3. `feat(cli): pagination and field projection`
4. `feat(cli): dry-run plans and confirmation for destructive commands`
5. `feat(cli): topic commands for every topic type with uploads` (one commit per 2–3 types is fine)
6. `feat(cli): access, settings and theme commands`
7. `feat(cli): long-running operations and ulams operations`
8. `feat(cli): declarative apply and get -o yaml`
9. `feat(cli): coverage matrix and exclusions; CI check`
10. `docs(cli): nouns, apply and examples`

DoD M2: coverage 100 % covered-or-excluded; e2e scenario 12.3 passes locally; apply idempotency test.

**M3 `phase-7/cli-mcp`**
1. `feat(cli): MCP server over stdio from the registry`
2. `feat(cli): MCP toolsets, read-only mode and meta tools`
3. `feat(cli): MCP confirmation for destructive tools`
4. `feat(cli): MCP Streamable HTTP transport with bearer auth`
5. `feat(cli): MCP resources for courses and builder sessions`
6. `test(cli): MCP client tests`
7. `docs(cli): agent guide`
8. `test(cli): agent eval harness and tasks`

DoD M3: MCP tests green; eval ≥ 4/5 in both modes (report committed); Claude Code demo recorded in the PR.

**S1 `phase-7/scoped-tokens`** (PHP; one PR, or two: tokens+scopes, then audit+idempotency)
1. `feat(auth): scoped personal access tokens and token endpoints` (migration, service, controller, policies, Swagger, tests, isolation)
2. `feat(auth): enforce token scopes from a route map` (+ coverage test, export command, JSON)
3. `feat(auth): agent audit log`
4. `feat(core): Idempotency-Key and X-Request-Id middleware`
5. `feat(core): capabilities endpoint /api/meta`
6. `feat(admin): API tokens page` + `feat(web): my tokens`
7. `feat(cli): tokens commands and scope errors`
8. `docs: API tokens and scopes`

**S2 `phase-7/device-login`**: `feat(auth): device authorization endpoints` → `feat(web): CLI authorization page` → `feat(cli): browser login with the device flow` → `docs: CLI login`.

**S3 `phase-7/platform-api`**: `refactor(tenancy): provision tenants in a queued job` → `feat(tenancy): platform tenant API` → `feat(cli): tenants commands` → `docs: platform API`.

**S4 `phase-7/builder-run-status`**: `feat(course-builder): run status endpoint` (+ Swagger, tests, isolation) → `feat(sdk): runs.get`.

**M4** `phase-7/cli-builder`: builder commands (one commit per group in 7.4), event stream, composite
`builder create`, e2e with mocked LLM, docs. `phase-7/cli-living` after the Phase 3 merge.

**M5** `phase-7/course-as-code`: its own plan section (written when Phase 3 merges) → Blueprint v2 schema
→ format parser and `validate` → `init`/`pull` → `push`/`preview`/`diff` → sync and conflicts → `publish`
→ docs.

**M6** `phase-7/cli-release`: `build(cli): bundle and npm publish workflow` → `build(cli): bun-compiled
binaries with checksums and signatures` → `build(cli): Docker image` → `docs: install`.

---

## 14. New dependencies

| Package | Where | Licence | Maintenance (2026-10) | Size | Why / alternative rejected |
|---|---|---|---|---|---|
| `zod` 4.x | cli runtime | MIT | very active | ~6 MB unpacked, ~60 kB in bundle | one schema type for parser, validation, `z.toJSONSchema`, MCP SDK v2 input; the SDK already lists it as optional peer |
| `yaml` 2.x | cli runtime | ISC | active; already in the repo (docs-site) | 0.7 MB | manifests, course-as-code |
| `@modelcontextprotocol/server` 2.x + `@modelcontextprotocol/node` 2.x | cli runtime | MIT (existing) / Apache-2.0 (new) | official, active | ~6.5 MB unpacked; pulls `zod`, `@modelcontextprotocol/core` | spec 2026-07-28; v1 `@modelcontextprotocol/sdk` (MIT) is the fallback |
| `@modelcontextprotocol/client` 2.x | cli dev | as above | | | MCP tests |
| `tsup` 8.x | cli dev | MIT | maintained | 0.4 MB | bundling the SDK source; esbuild underneath |
| `bun` (CI tool, `oven-sh/setup-bun`) | release CI only | MIT | very active | not shipped | cross-compiled binaries |

Not added: a CLI framework (commander, yargs, citty, oclif): `node:util.parseArgs` plus our help renderer
keeps exit codes, errors and JSON output fully ours and adds no runtime dependency. No prompt library:
interactive prompts are rare (login, confirmation) and `node:readline` suffices. No keychain module (native,
breaks cross-compilation). No `diff` library: JSON diffs are structural (own 80 lines); text diffs for
Markdown use a small Myers implementation or are deferred to M5 (then evaluate `diff`, BSD-3-Clause).

Self-hosting impact: none on the server image; the CLI is a client. Air-gapped installs use the binary or
the Docker image; the CLI never contacts anything but the configured instance.

---

## 15. ADRs proposed with this plan

| ADR | Title |
|---|---|
| 0072 | The agent-first `ulams` CLI: one command registry generates the parser, help, `describe`, MCP tools and docs |
| 0073 | CLI machine contract: JSON envelope, NDJSON, exit codes and error codes |
| 0074 | Scoped personal access tokens with an agent audit log and `Idempotency-Key` |
| 0075 | Device login with our own RFC 8628 flow approved in the web app; Passport's device grant stays off |
| 0076 | `ulams mcp`: a local MCP server (spec 2026-07-28, SDK v2) generated from the CLI registry |
| 0077 | CLI distribution: npm, bun-compiled binaries and a Docker image; no telemetry |
| 0078 | A platform-only HTTP API for tenant management |
| 0079 | Course-as-code: Markdown with directives + YAML, Blueprint v2 and a committed sync base |

---

## 16. Risks

| Risk | Mitigation |
|---|---|
| Generated nouns have poor names or unusable inputs (untyped bodies, multipart-as-POST updates) | overrides file reviewed in M2; hand-written commands for the worst offenders; `ulams api` fallback; coverage matrix shows untyped commands |
| 70 % untyped responses make MCP `outputSchema` absent | `UNTYPED_OUTPUT` warning; S5 types the top 60; models still get the JSON |
| Too many MCP tools degrade agent accuracy | toolsets with a small `core`, `commands_search`/`run` meta tools; eval measures it |
| Scope enforcement breaks existing clients | only tokens with a meta row are scoped; login/LTI/demo tokens unchanged; fail-closed applies only to scoped tokens |
| Static `Passport::personalAccessTokensExpireIn` races under concurrency | set and restore in `finally`; no Octane today; test |
| Device-code phishing (attacker sends a victim a code) | approval page shows client name, IP, scopes; codes expire in 10 min; approval throttled; tokens visible and revocable; audit log |
| Platform API widens the attack surface | off by default, platform hosts only, 30-day tokens, confirm on delete, audit |
| Bun runtime differences | fetch-only command code, Node is the reference runtime, binary smoke tests |
| MCP spec churn (2026-07-28 is new, clients lag) | SDK v2 negotiates versions; v1 fallback; stdio is the widely supported path |
| Phase 3 branch not pushed; M4b/M5 blocked | M4b/M5 explicitly after the merge; nothing earlier depends on them |
| Day-one expectation | M1–M3 need no server change (password/demo/pasted tokens); S1/S2 follow |

---

## 17. Decisions

### Decided

1. (2026-10-09, product owner) Build the CLI core (login and tokens, `ulams api`, the main nouns, `ulams mcp`)
   now; course-as-code after Phase 3. Milestone order M1 core → M2 nouns → M3 MCP → M4 builder and Living
   Course → M5 course-as-code. This also answers the open TODO question "Move MCP server (7.5) right after
   Phase 2?" with yes for the local MCP server. (#73, closed)

### Taken (to confirm with this plan)

2. TypeScript workspace `front/cli`, package and bin `ulams`, built on `@ulams/sdk` and bundled with tsup (ADR 0072).
3. One registry with zod schemas drives parser, help, `schema`/`describe`, MCP tools, docs and coverage (ADR 0072).
4. No CLI framework: `node:util.parseArgs` + own help (section 14).
5. Noun commands are generated from OpenAPI by method + path with a curated overrides file; hand-written where the generic command is unusable (4.4).
6. Output contract `contract: 1`: envelope on stdout, NDJSON for streams and `--all`, the exit-code table of 4.6, error codes with hints (ADR 0073).
7. Default output `auto`: human on a TTY, JSON when stdout is piped (agents get JSON even without `--json`; the guide still says to pass it).
8. Never prompt unless `--interactive` (or `login` on a TTY); every prompt is a flag; `--input @file` for whole inputs.
9. Destructive commands need `--yes` non-interactively (exit 11 otherwise); `--dry-run` everywhere with client-side diffs, server-side previews where only the server knows.
10. `--wait` is the default for long-running commands, timeout 600 s; `--no-wait` returns an operation handle.
11. Credentials in a 0600 file per profile, env vars for CI/agents, no `--token` flag, no keychain yet; tokens shown once.
12. Scoped personal access tokens on Passport PATs with `area:read|write` scopes, fail-closed route map, presets, `ulams_pat_` prefix (ADR 0074).
13. Agent audit log for scoped-token requests (no bodies), retention 365 days (ADR 0074).
14. `Idempotency-Key` middleware (24 h, mismatch 422, in-progress 409) and `X-Request-Id` (ADR 0074).
15. Device login: own RFC 8628-shaped flow, approval page in the web app (ADR 0075; default, pending #74).
16. MCP: local `ulams mcp` first, stdio default and loopback Streamable HTTP, spec 2026-07-28 with SDK v2; the Cloudflare Workers item becomes the later hosted variant (ADR 0076; default, pending #75).
17. MCP destructive confirmation: confirm token by default, MRTR elicitation where the client supports it.
18. MCP toolsets with a small `core` default plus `commands_search`/`commands_describe`/`commands_run`.
19. Distribution: npm first, bun-compiled binaries, Docker image; unsigned macOS/Windows binaries until an Apple/Windows signing identity exists (ADR 0077; default, pending #76); npm name `ulams` (pending #77).
20. No telemetry.
21. Platform tenant API, off by default, platform hosts only (ADR 0078; default, pending #79).
22. Course-as-code: Markdown with container directives + YAML, Blueprint v2 for non-RichText topics, committed `.ulams/state.json`, conflicts as diff files (ADR 0079); author blocks may skip citations (default, pending #78).
23. Webhooks stay in 7.3; the CLI only reserves `ulams webhooks` (7.9).
