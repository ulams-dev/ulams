# knowledge-export: cited knowledge packs for company RAG

Exports a course, or every published course of an academy, as chunked text with citations and metadata
(Phase 7.6 of `docs/ROADMAP-PROMPT.md`). Plan: `docs/plans/phase-7.md`.

- Guide: `front/docs-site/src/content/docs/developers/knowledge-export.mdx`
- Endpoints: `api/admin/knowledge/*` (permission `knowledge_manage`, token scope `courses:read`), OpenAPI tag "Admin Knowledge".
- Public schemas: `resources/schemas/knowledge-chunk-1.json`, `knowledge-manifest-1.json` (published on the docs site).
- Event: `KnowledgeExported`, delivered as the `knowledge.exported` webhook.

```
CoursePackBuilder   active rich-text topics -> Chunker (Fragmenter rules) -> chunks
CitationResolver    applied blueprint blocks matched word for word -> source fragments (else uncited)
KnowledgeExporter   writes manifest.json + chunks.jsonl (tenant: index.json + one pack per course)
ChangeListener      TopicTypeChanged / CourseStatusChanged -> queued re-export of a course exported before
```

Install: registered in `api/config/app.php`; run the migrations and `PermissionsSeeder`. Tests:
`php artisan test --testsuite knowledge-export`.
