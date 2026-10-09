// Reference pages generated from the code on every build, so they cannot fall behind it:
// packages (one page each, from its README plus what the code declares), permissions,
// administrable settings, environment variables, events and notification templates,
// artisan commands, scheduled jobs, API endpoints, topic types and the UI catalogue.
import { cell, exists, firstParagraph, plain, read, rewriteLinks, splitTitle, writePage, BLOB, EDIT, list, isDir, walk, demoteHeadings } from "../lib.mjs";
import {
  commands,
  envCalls,
  events,
  packageNames,
  permissions,
  routes,
  schedules,
  settings,
  templateRegistrations,
} from "../lib-code.mjs";

const code = (s) => (s ? `\`${String(s).replace(/`/g, "'")}\`` : "");
const fileLink = (file, label) => `[${label ?? file.replace(/^api\/packages\/[^/]+\//, "")}](${BLOB}/${file})`;
const pkgLink = (owner) => (owner === "app" ? "app" : `[${owner}](/reference/packages/${owner}/)`);
const table = (head, rows) =>
  rows.length
    ? `| ${head.join(" | ")} |\n|${head.map(() => "---").join("|")}|\n${rows.map((r) => `| ${r.map(cell).join(" | ")} |`).join("\n")}`
    : "_None._";
const groupBy = (items, key) => {
  const m = new Map();
  for (const i of items) {
    const k = typeof key === "function" ? key(i) : i[key];
    if (!m.has(k)) m.set(k, []);
    m.get(k).push(i);
  }
  return [...m.entries()].sort(([a], [b]) => (a === "app" ? -1 : b === "app" ? 1 : a.localeCompare(b)));
};

const APPS = [
  { key: "api-h5p", dir: "api/h5p", title: "api/h5p (H5P service)" },
  { key: "api-pdf", dir: "api/pdf", title: "api/pdf (PDF service)" },
  { key: "web", dir: "front/web", title: "front/web (reference frontend)" },
  { key: "admin", dir: "admin", title: "admin (admin panel)" },
  { key: "front", dir: "front", title: "front (legacy learner app)" },
  { key: "api", dir: "api", title: "api (Laravel application)" },
];

export default function generate() {
  const written = [];
  const pkgs = packageNames();
  const versions = exists("api/packages/versions.json") ? JSON.parse(read("api/packages/versions.json")) : {};
  const R = routes();
  const P = permissions();
  const S = settings();
  const E = events();
  const T = templateRegistrations();
  const C = commands();
  const J = schedules();
  const V = envCalls();
  const topicTypesByPackage = {};
  for (const p of pkgs) {
    for (const f of walk(`api/packages/${p}/src`, (x) => x.endsWith("ServiceProvider.php"))) {
      for (const m of read(f).matchAll(/registerContentClass(?:es)?\(\s*(\[[\s\S]*?\]|[^)]*)\)/g)) {
        for (const c of m[1].matchAll(/(\w+)::class/g)) (topicTypesByPackage[p] ??= []).push(c[1]);
      }
    }
  }

  const by = (arr, owner) => arr.filter((x) => x.owner === owner);
  const routeRows = (rs) => rs.map((r) => [code(r.method), code(r.path), r.auth ? "yes" : "", code(r.action)]);
  const permRows = (ps) => ps.map((p) => [code(p.name), [...p.roles].sort().join(", "), code(`${p.enumClass}::${p.constName}`)]);
  const settingRows = (ss) => ss.map((s) => [code(s.key), s.rules, s.public ? "yes" : "no", s.readonly ? "yes" : ""]);
  const scheduleRows = (js) => js.map((j) => [j.kind, code(j.target), code(j.chain.join("->"))]);
  const commandRows = (cs) => cs.map((c) => [code(c.name), c.description, code(c.signature)]);

  // ---- packages ----------------------------------------------------------------------
  const summaries = {};
  pkgs.forEach((p, i) => {
    const readmePath = `api/packages/${p}/README.md`;
    const readme = exists(readmePath) ? splitTitle(read(readmePath)) : { title: null, body: "" };
    const summary = plain(firstParagraph(readme.body), 300) || "No README yet.";
    summaries[p] = summary;
    const evs = by(E, p);
    const tpls = T.filter((t) => t.owner === p || evs.some((e) => e.name === t.event));
    const envs = V.filter((v) => v.owners.has(p));
    const extraDocs = [
      ...(exists(`api/packages/${p}/ADMIN.md`) ? [`api/packages/${p}/ADMIN.md`] : []),
      ...(isDir(`api/packages/${p}/docs`) ? list(`api/packages/${p}/docs`).map((f) => `api/packages/${p}/docs/${f}`) : []),
    ];
    const topicTypes = topicTypesByPackage[p] ?? [];
    const body = [
      `Source: [\`api/packages/${p}\`](https://github.com/ulams-dev/ulams/tree/main/api/packages/${p})${versions[`ulams/${p}`] ? ` · imported version ${code(versions[`ulams/${p}`])}` : ""}. The sections after the README are extracted from the code on every docs build.`,
      readme.body.trim()
        ? `## README\n\n${demoteHeadings(rewriteLinks(readme.body, readmePath))}`
        : "## README\n\n_This package has no README._",
      extraDocs.length ? `## More documentation in the package\n\n${extraDocs.map((f) => `- ${fileLink(f, f)}`).join("\n")}` : "",
      topicTypes.length ? `## Topic types\n\n${topicTypes.map((t) => `- ${code(t)}`).join("\n")}` : "",
      `## API endpoints\n\n${table(["Method", "Path", "Auth", "Action"], routeRows(by(R, p)))}`,
      `## Permissions\n\n${table(["Permission", "Seeded for roles", "Constant"], permRows(by(P, p)))}`,
      `## Settings\n\nRegistered with \`AdministrableConfig::registerConfig\` (editable in the admin panel under Configuration → Settings).\n\n${table(["Key", "Rules", "Public", "Read-only"], settingRows(by(S, p)))}`,
      `## Events\n\n${table(["Event", "Description", "Notification templates"], evs.map((e) => [code(e.name), e.summary, T.filter((t) => t.event === e.name).map((t) => t.channel).join(", ")]))}`,
      tpls.length ? `## Notification templates registered here\n\n${table(["Event", "Channel", "Variables"], tpls.filter((t) => t.owner === p).map((t) => [code(t.event), t.channel, code(t.variables)]))}` : "",
      `## Artisan commands\n\n${table(["Command", "Description", "Signature"], commandRows(by(C, p)))}`,
      `## Scheduled jobs\n\n${table(["Kind", "Target", "Frequency"], scheduleRows(by(J, p)))}`,
      `## Environment variables read\n\n${envs.length ? envs.map((v) => code(v.name)).join(", ") : "_None._"}`,
    ]
      .filter(Boolean)
      .join("\n\n");
    written.push(
      writePage(
        `reference/packages/${p}.md`,
        {
          title: `Package: ${p}`,
          description: plain(`The ${p} package: ${summary}`, 160),
          generatedFrom: `api/packages/${p}`,
          editUrl: exists(readmePath) ? `${EDIT}/${readmePath}` : false,
          sidebar: { label: p, order: i + 1 },
        },
        body
      )
    );
  });

  written.push(
    writePage(
      "reference/packages/index.md",
      {
        title: "API packages",
        description: `Every one of the ${pkgs.length} domain packages in api/packages with its purpose and what it declares: endpoints, permissions, settings, events, commands.`,
        generatedFrom: "api/packages/*",
        editUrl: false,
        sidebar: { label: "All packages", order: 0 },
      },
      `The API is composed of ${pkgs.length} packages in [\`api/packages\`](https://github.com/ulams-dev/ulams/tree/main/api/packages) (namespace \`Ulams\\\`), registered in \`api/config/app.php\`. The narrative guide is [API packages](/developers/api-packages/); this index is built from the code.\n\n${table(
        ["Package", "Purpose", "Endpoints", "Permissions", "Settings", "Events", "Commands"],
        pkgs.map((p) => [
          `[${p}](/reference/packages/${p}/)`,
          plain(summaries[p], 160),
          String(by(R, p).length),
          String(by(P, p).length),
          String(by(S, p).length),
          String(by(E, p).length),
          String(by(C, p).length),
        ])
      )}`
    )
  );

  // ---- apps (READMEs of the services and front-ends) ---------------------------------
  APPS.forEach((a, i) => {
    const path = `${a.dir}/README.md`;
    if (!exists(path)) return;
    const { body } = splitTitle(read(path));
    written.push(
      writePage(
        `reference/apps/${a.key}.md`,
        {
          title: a.title,
          description: plain(`${a.title}: ${plain(firstParagraph(body), 200)}`, 160),
          generatedFrom: path,
          editUrl: `${EDIT}/${path}`,
          sidebar: { order: i + 1 },
        },
        rewriteLinks(body, path)
      )
    );
  });

  // ---- permissions -------------------------------------------------------------------
  written.push(
    writePage(
      "reference/permissions.md",
      {
        title: "Permissions",
        description: `All ${P.length} permissions declared by the API packages, with the roles their seeders grant them to by default.`,
        generatedFrom: "api/packages/*/src/Enum(s)/*Permission*.php and seeders",
        editUrl: false,
        sidebar: { order: 2 },
      },
      `Permissions are [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) permissions on the \`api\` guard, declared as constants in each package's permission enum and created by its seeder, which also grants them to the default roles (\`admin\`, \`tutor\`, \`student\`). Roles can be changed per tenant in the admin panel: [Roles and permissions](/admin/roles-and-permissions/). The "Seeded for roles" column shows the seeders' defaults, not a tenant's current state.\n\n${groupBy(P, "owner")
        .map(([owner, ps]) => `## ${owner}\n\nPackage: ${pkgLink(owner)}\n\n${table(["Permission", "Seeded for roles", "Constant"], permRows(ps))}`)
        .join("\n\n")}`
    )
  );

  // ---- settings ----------------------------------------------------------------------
  written.push(
    writePage(
      "reference/settings.md",
      {
        title: "Settings keys",
        description: `All ${S.length} administrable settings keys registered by the API packages: validation rules, and whether they are public or read-only.`,
        generatedFrom: "AdministrableConfig::registerConfig calls in api/packages",
        editUrl: false,
        sidebar: { order: 3 },
      },
      `Packages register config keys with \`AdministrableConfig::registerConfig(key, rules, public, readonly)\` (package [settings](/reference/packages/settings/)). Registered keys can be changed per tenant in the admin panel ([Settings](/admin/settings/)) and override the config file value; **public** keys are returned to unauthenticated clients by \`GET /api/config\`, the others only to administrators. Keys shown with \`<name>\` are built in a loop in the code.\n\n${groupBy(S, "owner")
        .map(([owner, ss]) => `## ${owner}\n\nPackage: ${pkgLink(owner)}\n\n${table(["Key", "Rules", "Public", "Read-only"], settingRows(ss))}`)
        .join("\n\n")}`
    )
  );

  // ---- environment variables -----------------------------------------------------------
  const envDoc = splitTitle(read("api/docs/enviromental-variables.md"));
  written.push(
    writePage(
      "reference/environment-variables.md",
      {
        title: "Environment variables",
        description: `Environment variables of the API: the curated guide from api/docs plus every one of the ${V.length} env() reads found in the code.`,
        generatedFrom: "api/docs/enviromental-variables.md and env() calls in api/",
        editUrl: `${EDIT}/api/docs/enviromental-variables.md`,
        sidebar: { order: 4 },
      },
      `The first part is [\`api/docs/enviromental-variables.md\`](${BLOB}/api/docs/enviromental-variables.md); the second is built from the code. Front-end and service variables (\`ULAMS_*\` for front/web, \`REACT_APP_*\` for admin, \`VITE_APP_*\` for the legacy front, H5P and PDF service settings) are described in [Self-hosting](/operators/self-hosting/) and the app READMEs under [Reference](/reference/).\n\n## Guide\n\n${demoteHeadings(rewriteLinks(envDoc.body, "api/docs/enviromental-variables.md"))}\n\n## Every env() read in the code\n\nVariables read with \`env()\` in \`api/config\`, \`api/app\` and the packages. Laravel's \`LARAVEL_*\` prefixed variables (see the guide above) are mapped onto config keys at runtime and are not listed here.\n\n${table(
        ["Variable", "Default(s) in code", "Read by"],
        V.map((v) => [code(v.name), [...v.defaults].map(code).join(" "), [...v.owners].sort().join(", ")])
      )}`
    )
  );

  // ---- events and notifications --------------------------------------------------------
  written.push(
    writePage(
      "reference/events-and-notifications.md",
      {
        title: "Events and notifications",
        description: `All ${E.length} domain events dispatched by the API packages and the ${T.length} notification templates (email, SMS, PDF, database) registered for them.`,
        generatedFrom: "api/packages/*/src/Events and Template::register calls",
        editUrl: false,
        sidebar: { order: 5 },
      },
      `Packages dispatch Laravel events from \`src/Events\`. The templates packages register a template type for an event and a channel with \`Template::register(Event, Channel, Variables)\`: when the event fires, every active template of that type is rendered with the event's variables and sent ([Templates](/admin/templates/), [Notifications](/admin/notifications/)). Events can also be consumed by your own listeners ([Extending ulams](/extending/)).\n\n## Notification templates\n\n${table(
        ["Event", "Channel", "Variables class", "Registered in"],
        T.map((t) => [code(t.event), t.channel, code(t.variables), pkgLink(t.owner)])
      )}\n\n## Events by package\n\n${groupBy(E, "owner")
        .map(
          ([owner, es]) =>
            `### ${owner}\n\n${table(
              ["Event", "Description", "Templates"],
              es.map((e) => [code(e.name), e.summary, T.filter((t) => t.event === e.name).map((t) => t.channel).join(", ")])
            )}`
        )
        .join("\n\n")}`
    )
  );

  // ---- artisan commands ------------------------------------------------------------
  written.push(
    writePage(
      "reference/artisan-commands.md",
      {
        title: "Artisan commands",
        description: `All ${C.length} artisan commands defined by the ulams API and its packages, with their signatures and descriptions.`,
        generatedFrom: "Command classes in api/app and api/packages",
        editUrl: false,
        sidebar: { order: 6 },
      },
      `Run them inside the API container: \`docker compose -f api/docker-compose.yml exec api php artisan <command>\`. In a multi-tenant install add \`--domain=<tenant host>\` to run a command for one tenant ([Tenancy](/developers/tenancy/)). Laravel's and third-party packages' own commands (\`migrate\`, \`queue:work\`, \`passport:*\`, \`horizon\`, …) are not listed; \`php artisan list\` shows everything.\n\n${groupBy(C, "owner")
        .map(([owner, cs]) => `## ${owner}\n\n${owner === "app" ? "" : `Package: ${pkgLink(owner)}\n\n`}${table(["Command", "Description", "Signature"], commandRows(cs))}`)
        .join("\n\n")}`
    )
  );

  // ---- scheduled jobs ----------------------------------------------------------------
  written.push(
    writePage(
      "reference/scheduled-jobs.md",
      {
        title: "Scheduled jobs",
        description: `The ${J.length} jobs and commands the API registers with the Laravel scheduler, with their frequency and the package that owns them.`,
        generatedFrom: "$schedule-> calls in api/app and api/packages",
        editUrl: false,
        sidebar: { order: 7 },
      },
      `The scheduler runs from [\`api/scheduler.sh\`](${BLOB}/api/scheduler.sh), which calls \`schedule:run\` for every tenant domain, so each entry below runs once per tenant. Conditions in the code (for example demo mode only on demo tenants) are not shown; follow the file link to see them.\n\n${table(
        ["Package", "Kind", "Target", "Frequency", "File"],
        J.map((j) => [pkgLink(j.owner), j.kind, code(j.target), code(j.chain.join("->")), fileLink(j.file)])
      )}`
    )
  );

  // ---- API endpoints ---------------------------------------------------------------
  written.push(
    writePage(
      "reference/api-endpoints.md",
      {
        title: "API endpoints",
        description: `Index of the ${R.length} HTTP routes the API packages declare, by package, with method, path, authentication and controller action.`,
        generatedFrom: "route definitions in api/routes and api/packages",
        editUrl: false,
        sidebar: { order: 1 },
        tableOfContents: { minHeadingLevel: 2, maxHeadingLevel: 2 },
      },
      `Request and response schemas are in the Swagger UI that every API host serves at \`/api/documentation\` ([API reference](/developers/api-reference/)). This index is extracted statically from the route files, so it lists routes even when their Swagger annotations are missing. Routes of third-party packages (Passport \`/oauth/*\`, Horizon \`/horizon/*\`, l5-swagger \`/api/documentation\` and \`/docs\`, broadcasting) are not included. "Auth" means the route or its group uses the \`auth:api\` middleware (Passport bearer token).\n\n${groupBy(R, "owner")
        .map(([owner, rs]) => `## ${owner}\n\n${owner === "app" ? "Application routes in `api/routes`." : `Package: ${pkgLink(owner)}`}\n\n${table(["Method", "Path", "Auth", "Action"], routeRows(rs))}`)
        .join("\n\n")}`
    )
  );

  return written;
}
