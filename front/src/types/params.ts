import { API } from "@ulams/sdk";

export type PackagesParams = API.PaginationParams & {
  type?: "single" | "bundle";
};
