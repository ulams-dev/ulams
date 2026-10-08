import { IStatement, PageParams, PaginationParams } from "./core";

/**
 * H5P content as embedded in topic / course program responses
 * (`topic.topicable.content`). The full player model is fetched separately
 * from the H5P service: GET `${apiUrl}/h5p/contents/:id/play`.
 */
export type H5PContent = {
  id: number | string;
  title: string;
  /** library with major.minor version, e.g. "H5P.MultiChoice 1.16" */
  library: string;
  /** machine name only, e.g. "H5P.MultiChoice" */
  main_library: string;
};

/** Admin list item: GET `${apiUrl}/api/admin/h5p/contents` */
export type H5PContentListItem = H5PContent & {
  user_id: number | string | null;
  created_at: string;
  updated_at: string;
  count_h5p: number;
};

export type H5PContentParams = PageParams &
  PaginationParams & {
    title?: string;
    order_by?: string;
    order?: "ASC" | "DESC";
  };

/** Query options of the H5P service's player embed page */
export type H5PEmbedPlayParams = {
  language?: string;
  contextId?: string;
  readOnlyState?: boolean;
  hideActions?: boolean;
};

/**
 * postMessage protocol with the H5P service embed pages
 * (`/h5p/embed/play/:id`, `/h5p/embed/edit/:id|new`); see api/h5p/README.md.
 */
export type H5PEmbedToParent =
  | { type: "ulams-h5p:ready"; mode: "play" | "edit"; contentId: string }
  | { type: "ulams-h5p:loaded"; contentId: string; title?: string; library?: string }
  | { type: "ulams-h5p:resize"; height: number }
  | { type: "ulams-h5p:xapi"; statement: IStatement; contentId: string }
  | { type: "ulams-h5p:saved"; contentId: string; metadata: unknown }
  | { type: "ulams-h5p:error"; message: string; code?: string };

export type H5PParentToEmbed =
  | { type: "ulams-h5p:token"; token: string | null }
  | { type: "ulams-h5p:style"; css?: string; urls?: string[] }
  | { type: "ulams-h5p:save" };

/** xAPI event forwarded by the H5P player component */
export type H5PXAPIEvent = {
  statement: IStatement;
  context?: { contentId: string };
};
