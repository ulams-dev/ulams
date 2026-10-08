import React from "react";
import { API } from "@ulams/sdk";

export const ConsultationsContext: React.Context<{
  consultations?: API.PaginatedMetaList<API.Consultation>;
  loading?: boolean;
  params?: API.ConsultationParams;
  setParams?: (params: API.ConsultationParams) => void;
}> = React.createContext({});
