import { useMemo } from "react";
import { JITSY_AI_MIN_PARTICIPANT_COUNT } from "@/utils/constants";
import { API } from "@ulams/sdk";
import { Consultation } from "@ulams/sdk/types";

export type ExtendedConsultation = Consultation & {
  analyze_enabled?: boolean;
};

export type ExtendedWebinar = API.Webinar & {
  analyze_enabled?: boolean;
};
interface UseJitsyAnalyticsControlProps {
  modelType: "consultation" | "webinar";
  participantCount: number;
  consultationData?: ExtendedConsultation;
  webinarData?: ExtendedWebinar;
}

export const useJitsyAnalyticsControl = ({
  modelType,
  participantCount,
  consultationData,
  webinarData,
}: UseJitsyAnalyticsControlProps) => {
  const isEnabledInBackend = useMemo(() => {
    if (modelType === "consultation") {
      return consultationData?.analyze_enabled;
    }
    return webinarData?.analyze_enabled;
  }, [modelType, consultationData, webinarData]);

  const hasEnoughParticipants =
    participantCount >= JITSY_AI_MIN_PARTICIPANT_COUNT;

  const shouldRunAnalytics = isEnabledInBackend && hasEnoughParticipants;

  return {
    shouldRunAnalytics,
    isEnabledInBackend,
    hasEnoughParticipants,
  };
};
