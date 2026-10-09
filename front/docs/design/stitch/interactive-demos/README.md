# Interactive demo landings (Stitch)

These are the landing designs for the three new demo academies in `docs/plans/interactive-demos.md`
(section 8): gravity, poland and ulam. They belong to the Stitch project "Wellms E-Learning Experience
System" (`9081291570384881657`), the same project that holds the coffee, oncall and nightsky screens
(`../screens/README.md`). The prompts are in [`PROMPTS.md`](PROMPTS.md).

| Folder | Prompt | Status |
|---|---|---|
| `gravity-landing` | Gravity Lab | Not generated: the request timed out on 2026-10-09 and the project showed no new screen afterwards |
| `poland-landing` | Poland, Measured / Polska w liczbach | Not generated (timed out) |
| `ulam-landing` | The Scottish Book | Not generated (timed out) |

**To regenerate a screen:**

1. Send its prompt with `generate_screen_from_text` (desktop).
2. Export the `code.html` and a `screen.webp` (1440 px wide, `cwebp -q 72 -m 6`) into a folder named
   as in the table.
3. Update the status column.

The HTML is reference material only. Landings are built from `@ulams/ui` catalogue components and the
`gravity`, `poland` and `ulam` theme presets (ADR 0004, plan M7).
