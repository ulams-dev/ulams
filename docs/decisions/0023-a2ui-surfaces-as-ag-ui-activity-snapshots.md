# 0023. A2UI surfaces travel as AG-UI activity snapshots (`a2ui-surface`)

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

ADR 0011 left open how A2UI v0.9 messages are carried inside AG-UI: as activity events if that is
the AG-UI convention, otherwise as `CUSTOM` events. `@ag-ui/core` 1.0.2 (checked 2026-10-09) defines
`ACTIVITY_SNAPSHOT` / `ACTIVITY_DELTA` (an activity message with a type, object content and a
`replace` flag) and has no A2UI-specific event or convention.

## Decision

- Each surface is one `ACTIVITY_SNAPSHOT` event: `messageId` = surface id, `activityType` =
  `a2ui-surface`, `replace: true`, `content = {surfaceId, kind, messages: [createSurface,
  updateComponents, updateDataModel?]}` with A2UI v0.9 messages and our catalogue id
  `https://ulams.dev/catalogue/builder/v1`. An update re-sends the whole surface; `deleteSurface`
  ends it.
- `kind` (`source`, `interview`, `outline`, `progress`, `apply`, `patch`) is our extension; the
  server records every issued surface and accepts an action only from an open surface of the
  matching kind (stale cards get a text reply).
- Actions come back as AG-UI `RunAgentInput` with the A2UI action in `forwardedProps.action`.
- One adapter on each side: `EventLog::surface()` in the API, `surfaceFromEvent()` in `@ulams/sdk`.

## Consequences

- Good: schema-valid AG-UI events (the SDK tests validate them with `@ag-ui/core/schemas`); other
  AG-UI clients see a well-formed activity even without A2UI support.
- Good: snapshots make replay and reconnection trivial.
- Bad: whole-surface snapshots are larger than deltas; acceptable at builder sizes. `ACTIVITY_DELTA`
  with JSON Patch can replace them later behind the same adapters.
