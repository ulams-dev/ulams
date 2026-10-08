import { useContext, useEffect, useState } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import {
  Consultation,
  CourseParams,
  PaginatedMetaList,
} from "@ulams/sdk/types";

const useFetchConsultations = (params?: CourseParams, noAutoFech?: boolean) => {
  const [consultations, setConsultations] =
    useState<PaginatedMetaList<Consultation & { analyze_enabled?: boolean }>>();
  const [loading, setLoading] = useState(true);
  const { fetchConsultations } = useContext(UlamsContext);

  const fetchConsultationsData = async (params: CourseParams) => {
    setLoading(true);
    try {
      const request = await fetchConsultations(params);

      if (request) {
        setConsultations(
          request as PaginatedMetaList<
            Consultation & { analyze_enabled?: boolean }
          >
        );
      }
    } catch (e) {
      console.error(e);
      setConsultations(undefined);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    params && !noAutoFech && fetchConsultationsData(params);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fetchConsultations]);

  return { consultations, loading, fetchConsultationsData };
};

export default useFetchConsultations;
