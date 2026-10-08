/**
 * postMessage protocol between the H5P embed pages served by this service
 * (`/h5p/embed/play/:id`, `/h5p/embed/edit/:id|new`) and the LMS frontends
 * that frame them. Every message is a plain object whose `type` starts with
 * `ulams-h5p:`. Both sides check `event.origin` (page: allow-list from
 * CORS_ORIGINS; parent: the service origin) and `event.source`.
 *
 * The frontends keep their own (MIT) copy of these types; keep them in sync
 * with the table in README.md.
 */

/** page → parent */
export type EmbedToParent =
    | { type: 'ulams-h5p:ready'; mode: 'play' | 'edit'; contentId: string }
    | { type: 'ulams-h5p:loaded'; contentId: string; title?: string; library?: string }
    | { type: 'ulams-h5p:resize'; height: number }
    | { type: 'ulams-h5p:xapi'; statement: unknown; contentId: string }
    | { type: 'ulams-h5p:saved'; contentId: string; metadata: unknown }
    | { type: 'ulams-h5p:error'; message: string; code?: string };

/** parent → page */
export type ParentToEmbed =
    /** access token (null = anonymous); send again after every refresh */
    | { type: 'ulams-h5p:token'; token: string | null }
    /** extra CSS for the content: inline text and/or stylesheet URLs on an allowed origin */
    | { type: 'ulams-h5p:style'; css?: string; urls?: string[] }
    /** editor only: save the content (answer: saved or error) */
    | { type: 'ulams-h5p:save' };

export const MESSAGE_PREFIX = 'ulams-h5p:';

/** JSON embedded in the page as <script id="ulams-h5p-config"> */
export interface EmbedConfig {
    mode: 'play' | 'edit';
    /** content id; "new" for the editor of new content */
    contentId: string;
    /** service mount path, e.g. "/h5p" (same origin as the page) */
    base: string;
    allowedOrigins: string[];
    language: string;
    contextId?: string;
    readOnlyState?: boolean;
    hideActions?: boolean;
}
