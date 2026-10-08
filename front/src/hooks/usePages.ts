import { UlamsContext } from "@ulams/sdk/react";
import { useContext, useEffect, useMemo } from "react";

export function usePages() {
  const { fetchPages, pages } = useContext(UlamsContext);

  useEffect(() => {
    fetchPages().then(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const collection = useMemo(() => {
    return pages?.list?.data || [];
  }, [pages.list]);

  return {
    collection,
  } as const;
}
