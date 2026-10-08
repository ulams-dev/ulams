import React from "react";
import { API } from "@ulams/sdk";
import { ContextPaginatedMetaState } from "@ulams/sdk/react/context/types";
import { PackagesParams } from "@/types/params";

export const PackagesContext: React.Context<{
  packages?: ContextPaginatedMetaState<API.Product>;
  params?: PackagesParams;
  setParams?: (params: PackagesParams) => void;
  onlyFree?: boolean;
}> = React.createContext({});
