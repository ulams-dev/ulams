# Topic type Layout

The **Layout** topic type ([ADR 0052](../../../docs/decisions/0052-learner-layouts-as-a-layout-topic-type.md)):
a lesson body made of approved learning components (timeline, flip cards, steps, callout, code listing,
comparison table, practice activity, H5P and LiaScript frames), stored as a validated JSON document with a
Markdown fallback.

- Model `Ulams\TopicTypeLayout\Models\LayoutTopic`, table `topic_layouts`: `document` (JSON list of
  `{component, props, id?}` nodes), `schema_version` (`1`) and `markdown_fallback`.
- **Validation.** `LayoutDocumentValidator` checks the document against
  `resources/learner-layout-manifest.json`: a non-empty list of at most 80 nodes, only the approved
  components, closed props schemas (`opis/json-schema`, `default` keywords ignored so required props
  stay required), safe `href` links, no children. The manifest is a copy of
  `front/ui/catalogue/learner-layout-manifest.json`; `yarn workspace @ulams/ui learner-manifest`
  rewrites both, and the UI tests and `LayoutDocumentValidatorTest` fail when they differ.
- **No routes.** Topics are created and changed through the topic API
  (`POST /api/admin/topics`, `topicable_type` = `Ulams\TopicTypeLayout\Models\LayoutTopic`, `document` as a
  JSON list, or as a JSON string in multipart forms, and `markdown_fallback`). The document is validated
  on every create and update. Permissions and tenant isolation are those of the topic API.
- **Export and import.** The document and the fallback are plain JSON in `content.json`; the import
  creates the topic through the topic API and validates again.
- Rendering is in `front/web` (the lesson player); other clients show `markdown_fallback`.
