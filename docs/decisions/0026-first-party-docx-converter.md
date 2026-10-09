# 0026. A first-party DOCX converter instead of PhpWord

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

Sources can be DOCX. `phpoffice/phpword` is LGPL-3.0-only and large; the builder needs headings,
paragraphs, lists, tables, code and links as Markdown, nothing else. DOCX files are zip archives and
untrusted (zip slip, zip bombs, XML entities).

## Decision

`Ulams\CourseBuilder\Ingestion\DocxConverter` (about 300 lines): the archive is checked by the uploads
package's `ZipInspector` with a builder size cap first; `word/document.xml`, `styles.xml`, the
relationships and `docProps/core.xml` are parsed with DOM (`LIBXML_NONET`, DOCTYPE rejected).
Headings from `Heading N`/`Title` styles or outline levels, lists from numbering, tables to Markdown
tables, monospace runs or code styles to code, `w:hyperlink` with http(s) targets to links, bold and
italics kept. Images, footnotes, comments and tracked changes are ignored.

## Consequences

- Good: no new dependency, no LGPL code in the API; the attack surface is small and tested
  (zip bomb fixture, DOCTYPE rejection).
- Bad: unusual documents (text boxes, nested tables, SmartArt) lose structure; they still become
  text the author can cite, and PDF export is an alternative.
