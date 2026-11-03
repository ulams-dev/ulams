/* The workflow `ulams course init --ci github` writes (plan 8.2): validation and a preview on every pull request, a push of the
 * merged course, and an hourly pull of changes made in ulams as a sync pull request. It uses the `ulams course` action of this
 * repository, which runs the CLI image, so nothing else has to be installed on the runner. */

export const WORKFLOW_FILE = ".github/workflows/ulams.yml";
export const ACTION = "ulams-dev/ulams/actions/course@cli-v1";

export interface WorkflowOptions {
  /** The course folder relative to the repository root ("." when the repository is the course). */
  path: string;
  /** Default branch the merged course is pushed from. */
  branch?: string;
}

export function githubWorkflow(o: WorkflowOptions): string {
  const path = o.path === "" ? "." : o.path;
  const branch = o.branch ?? "main";
  const filter = path === "." ? "" : `\n    paths: ["${path}/**", ".github/workflows/ulams.yml"]`;
  return `# Written by \`ulams course init --ci github\`. The action runs the ulams CLI image (${ACTION}).
# Repository secrets: ULAMS_URL (the academy API address) and ULAMS_TOKEN (a ci token, \`ulams tokens create --preset ci\`).
# Optional, for a preview academy per pull request: ULAMS_PREVIEW_URL and ULAMS_PREVIEW_TOKEN (a platform:write token of a preview
# platform, an ulams install with TENANCY_PLATFORM_API=true). Without them the pull request gets a proposed version instead.
name: ulams course

on:
  pull_request:
    types: [opened, synchronize, reopened, closed]${filter}
  push:
    branches: [${branch}]${filter}
  schedule:
    - cron: "17 * * * *" # pull what changed in ulams, hourly
  workflow_dispatch:
  repository_dispatch:
    types: [ulams-course-changed]

concurrency:
  group: ulams-course-\${{ github.event_name }}-\${{ github.ref }}
  cancel-in-progress: \${{ github.event_name == 'pull_request' }}

env:
  ULAMS_URL: \${{ secrets.ULAMS_URL }}
  ULAMS_TOKEN: \${{ secrets.ULAMS_TOKEN }}
  ULAMS_PREVIEW_URL: \${{ secrets.ULAMS_PREVIEW_URL }}
  ULAMS_PREVIEW_TOKEN: \${{ secrets.ULAMS_PREVIEW_TOKEN }}

jobs:
  validate:
    if: github.event_name == 'pull_request' && github.event.action != 'closed'
    runs-on: ubuntu-latest
    permissions:
      contents: read
      pull-requests: write
      security-events: write
    steps:
      - uses: actions/checkout@v4
      - id: validate
        uses: ${ACTION}
        with:
          command: validate
          path: ${path}
        continue-on-error: true
      - uses: github/codeql-action/upload-sarif@v3
        if: always() && steps.validate.outputs.sarif != ''
        with:
          sarif_file: \${{ steps.validate.outputs.sarif }}
          category: ulams-course
      - name: Fail when the course has errors
        if: steps.validate.outputs.exit-code != '0'
        run: exit 1
      # the next two need the academy; a pull request from a fork has no secrets, so they are skipped there
      - uses: ${ACTION}
        if: github.event.pull_request.head.repo.full_name == github.repository
        with:
          command: diff
          path: ${path}
      - uses: ${ACTION}
        if: github.event.pull_request.head.repo.full_name == github.repository
        with:
          command: preview
          path: ${path}

  cleanup:
    if: github.event_name == 'pull_request' && github.event.action == 'closed' && github.event.pull_request.head.repo.full_name == github.repository
    runs-on: ubuntu-latest
    permissions:
      contents: read
    steps:
      # the preview academy of the pull request goes with it (the reaper deletes it anyway when its time is up)
      - uses: ${ACTION}
        with:
          command: preview-cleanup

  push:
    if: github.event_name == 'push'
    runs-on: ubuntu-latest
    permissions:
      contents: read
    steps:
      - uses: actions/checkout@v4
      # a proposed version; a person approves it in the studio (add \`apply: true\` only if the academy lets ci tokens approve)
      - uses: ${ACTION}
        with:
          command: push
          path: ${path}

  sync:
    if: github.event_name == 'schedule' || github.event_name == 'workflow_dispatch' || github.event_name == 'repository_dispatch'
    runs-on: ubuntu-latest
    permissions:
      contents: write
      pull-requests: write
    steps:
      - uses: actions/checkout@v4
      - uses: ${ACTION}
        with:
          command: pull-pr
          path: ${path}
`;
}
