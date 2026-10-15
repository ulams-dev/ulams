# Tracking contract

All shells and building blocks remain ordinary ULAMS course topics. Frontend state is never the
source of truth for entitlement, score or completion.

## Normalized signals

- `viewed` — the element became meaningfully visible.
- `progressed` — progress 0–100 with a stable element/topic ID.
- `attempted` — a new attempt started.
- `answered` — a learner response was stored.
- `scored` — score and maximum score were stored.
- `completed` — the runtime's tested completion rule was met.
- `passed` / `failed` — a configured success rule was evaluated.
- `resumed` — saved state was restored.

Only emit signals the adapter supports. Never infer `completed` from an iframe load or a frontend
redirect.

## Adapters

- Native rich text/layout/media: ULAMS topic progress and completion rules.
- GIFT: quiz attempts, answers, score, completion and pass/fail from the server.
- H5P: captured xAPI/state through the ULAMS H5P bridge; completion differs by library and needs a
  conformance fixture.
- SCORM: CMI data through the SCORM runtime.
- cmi5: xAPI/cmi5 statements; progress is 0–100 and completed is a separate statement.
- LiaScript: launch/player state and the configured section/completion rule.
- LTI: launch plus AGS grade passback where the tool supports it.
- Curated interactive: typed `ulams-ix` bridge events.

## Publication checks

- Every trackable element has stable blueprint and LMS topic IDs.
- Resume works after refresh where claimed.
- Retrying an attempt does not overwrite history silently.
- Imported packages have a tested completion rule.
- A text fallback does not falsely inherit the richer runtime's score.
- Scrolling/slides report stable section progress, but course completion remains a server rule.
- Updates preserve progress under the Living Course rules; changed assessed answers trigger
  re-attempt rather than rewriting past scores.
