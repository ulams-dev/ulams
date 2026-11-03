# create-ulams

`npx create-ulams my-academy` sets up a local [ulams](https://github.com/ulams-dev/ulams) academy: private credentials,
a Docker Compose stack (database, cache, API, workers, learner site, admin, H5P, PDF, object storage, gateway), the first
academy and a few demo students. It checks the machine and the directory, then runs `ulams stack up`
(the stack launcher, ADR 0102) with the right options; the CLI does the work.

```bash
npx create-ulams my-academy                       # local trial, http://*.ulams.localhost:8480, 3 demo students
npx create-ulams my-academy --users 10 --theme nightsky --academy-name "Night School"
npx create-ulams prod --mode production --domain learn.example.org
npx create-ulams my-academy --dry-run             # show the plan, change nothing
```

Needs Node 22.12+ and Docker Compose v2 (`--target kubernetes` needs kubectl access instead). The directory must be new
or empty; `--force` resumes a stack started there earlier. Manage it afterwards with
`npx ulams stack status|down --directory my-academy` (`down` keeps your data).

The seed data is what the ulams tenant provisioning creates: the academy with its name and theme, an admin and
`--users` demo students (default 3 in development). Production keeps the launcher's defaults and starts with AI disabled.

## Development

`yarn workspace create-ulams test` runs the unit tests and an integration test against the built CLI
(`yarn workspace ulams build` first; it is skipped otherwise). The package depends on `ulams` at the same version, so
publishing waits for the CLI's npm release (issue #77); until then use `--cli front/cli/dist/ulams.mjs`.
