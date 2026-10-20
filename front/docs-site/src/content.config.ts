import { defineCollection } from "astro:content";
import { z } from "astro/zod";
import { docsLoader } from "@astrojs/starlight/loaders";
import { docsSchema } from "@astrojs/starlight/schema";

/**
 * Starlight's schema plus the fields read by the coverage check (scripts/coverage.mjs) and the
 * title badges (src/components/PageTitle.astro).
 */
export const collections = {
  docs: defineCollection({
    loader: docsLoader(),
    schema: docsSchema({
      extend: z.object({
        /** api/packages/* names and app keys (admin, front, web, sdk, ui, api, api-h5p, api-pdf) this page documents. */
        modules: z.array(z.string()).optional(),
        /** Admin routes (admin/config/routes.ts) this page documents; a trailing `/**` matches a subtree. */
        adminRoutes: z.array(z.string()).optional(),
        /** Learner routes (front/web/src/pages) this page documents; a trailing `/**` matches a subtree. */
        learnerRoutes: z.array(z.string()).optional(),
        /** Topic types (class names, e.g. RichText) this page documents. */
        topicTypes: z.array(z.string()).optional(),
        /** The feature is on the roadmap, not in the code yet. */
        coming: z.boolean().optional(),
        /** Documented from the code but not verified end to end; a string says what to check. */
        needsReview: z.union([z.boolean(), z.string()]).optional(),
        /** Generated pages: the repository files the content comes from. */
        generatedFrom: z.string().optional(),
      }),
    }),
  }),
};
