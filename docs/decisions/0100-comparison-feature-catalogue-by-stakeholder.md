# 0100. Restore the Comparison feature catalogue by stakeholder

- Status: Proposed
- Date: 2026-10-12

## Context and problem statement

The redesign replaced the previous detailed feature index with four brief stakeholder cards.
The owner requested the previous descriptions on Comparison, organised around the people
using the product. The previous catalogue contains 101 descriptions in fourteen categories
and 54 named H5P content types.

## Decision

- Restore all descriptions and documentation references from the previous catalogue.
- Assign each category to one primary stakeholder: learners, authors/L&D, learning owners,
  sales/customer teams, administrators, or IT/integrators/agents.
- Provide stakeholder anchor navigation and native category disclosures. The page works
  without JavaScript, supports the keyboard and renders all content for search engines.
- Emit ItemList JSON-LD and keep EN/PL/ZH category structure parallel.
- Keep the sourced competitor matrix on Comparison and retain feature-status distinctions.
- Use the existing UI catalogue and schema renderer, with no additional dependency.

## Consequences

Comparison becomes a complete capability reference while category disclosures limit how much
text is shown at once. Package coverage and feature counts are checked in tests. Overview keeps
its short product narrative and demos.
