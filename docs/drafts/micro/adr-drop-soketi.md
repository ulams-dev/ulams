# Drop Soketi until realtime is needed

- Status: Proposed
- Date: 2026-11-01
- Plan: `docs/plans/realtime-features.md`

## Context and problem statement

The current broadcast driver is set to log, and Soketi, the WebSocket server, is running but unused. This setup leads to unnecessary resource consumption and potential confusion in the development environment.

## Considered options

1. **Remove Soketi now and adopt Laravel Reverb when the first realtime feature lands**: This option simplifies the current setup by removing unused services and prepares for future needs by using a well-integrated solution.
2. **Keep Soketi for potential future use**: This option maintains the current setup, preserving the possibility of using Soketi in the future without any immediate changes.
3. **Replace Soketi with another WebSocket server**: This option involves finding and integrating a different WebSocket server that could potentially offer more features or better performance.

## Decision

Option 1. Remove Soketi from the compose profiles now. Adopt Laravel Reverb when the first realtime feature is implemented. This decision aligns with the current needs and simplifies the setup, while also preparing for future requirements.

## Consequences

- Good: Fewer running containers, reducing resource consumption and potential confusion.
- Good: Easier maintenance and a clearer development environment.
- Bad: A later migration cost when adopting Laravel Reverb for the first realtime feature.
- Bad: Potential delays if a critical feature requiring Soketi is needed before the migration to Laravel Reverb.

## Alternatives considered

- Option 2 keeps the current setup, which could lead to unnecessary resource consumption and potential confusion.
- Option 3 involves finding and integrating a different WebSocket server, which could introduce additional complexity and potential compatibility issues.
