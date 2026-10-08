import React, { useContext, useEffect, useState } from "react";

import { useLocation, useHistory } from "react-router-dom";
import { UlamsContext } from "@ulams/sdk/react/context";
import { API } from "@ulams/sdk";
import qs from "query-string";
import { WebinarsContext } from "./WebinarsContext";

export type WebinarsParams = API.WebinarParams & {
  "tags[]"?: string[];
};

const parseParams = (params: WebinarsParams = {}) => {
  return qs.stringify(params);
};

const WebinarsProvider: React.FC<{
  children: React.ReactNode;
}> = ({ children }) => {
  const { fetchWebinars, webinars, fetchTags } = useContext(UlamsContext);
  const location = useLocation();
  const { push } = useHistory();
  const [params, setParams] = useState<WebinarsParams | undefined>();
  const [webinarData, setWebinarData] = useState<API.Webinar | null>(null);
  const [isModalOpen, setModalOpen] = useState(false);

  const getApiParams = (params: WebinarsParams = {}): WebinarsParams => {
    return {
      page: 1,
      per_page: 8,
      ...params,
    };
  };

  useEffect(() => {
    fetchTags();
  }, [fetchTags]);

  useEffect(() => {
    push(`${location.pathname}?${parseParams(params)}`);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [params, location.pathname]);

  useEffect(() => {
    if (
      location.search &&
      location.search.split("?")[1] !== parseParams(params)
    ) {
      const parsedParams = qs.parse(location.search);
      setParams(parsedParams);
      fetchWebinars(parsedParams || {});
    } else {
      fetchWebinars(getApiParams(params));
    }
  }, [fetchWebinars, location.search, params]);

  return (
    <WebinarsContext.Provider
      value={{
        params,
        setParams,
        webinars: webinars,
        webinarData,
        setWebinarData,
        isModalOpen,
        setModalOpen,
      }}
    >
      {children}
    </WebinarsContext.Provider>
  );
};

export default WebinarsProvider;
