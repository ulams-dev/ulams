import { API } from "@lms/sdk";

export type PackagesParams = API.PaginationParams & {
  type?: "single" | "bundle";
};
