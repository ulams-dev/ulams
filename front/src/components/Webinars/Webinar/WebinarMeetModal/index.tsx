import { useCallback, useContext, useEffect, useRef, useState } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import { Modal } from "@ulams/components/components/atoms/Modal/Modal";
import { JitsyData } from "@ulams/sdk/types";
import ContentLoader from "@/components/_App/ContentLoader";
import { WebinarMeetModalStyles } from "./WebinarMeetModalStyles";
import { useTranslation } from "react-i18next";
import { toast } from "@/utils/toast";
import styled from "styled-components";
import JitsyMeeting from "@/components/Consultations/ConsultationCard/JitsyMeeting";
import { EndMeetingQuestionnairesModal } from "@/components/Consultations/ConsultationCard/EndMeetingQuestionnaires";
import { QuestionnaireModelType } from "@/types/questionnaire";

interface Props {
  onClose: () => void;
  visible: boolean;
  webinarId: number;
}

const JitsiContainer = styled.div`
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
`;

const WebinarMeetModal = ({ onClose, visible, webinarId }: Props) => {
  const [webinarMeetData, setWebinarMeetData] = useState<JitsyData | null>(
    null
  );
  const [loading, setLoading] = useState(false);
  const [isEnded, setIsEnded] = useState(false);
  const onCloseRef = useRef(onClose);
  const { generateWebinarJitsy } = useContext(UlamsContext);
  const { t } = useTranslation();

  useEffect(() => {
    onCloseRef.current = onClose;
  }, [onClose]);

  useEffect(() => {
    const getMeetUrl = async () => {
      if (!webinarId || !visible || webinarMeetData) return;

      setLoading(true);
      try {
        const res = await generateWebinarJitsy(webinarId);
        if (res.success) {
          setWebinarMeetData((res as { data: JitsyData }).data);
        } else {
          toast(t("WebinarPage.ErrorWhileGeneratingUrl"), "error");
          onCloseRef.current();
        }
      } catch (error) {
        console.error("Error generating Jitsi URL:", error);
        toast(t("WebinarPage.ErrorWhileGeneratingUrl"), "error");
      } finally {
        setLoading(false);
      }
    };
    getMeetUrl();
  }, [webinarId, visible, generateWebinarJitsy, t, webinarMeetData]);

  useEffect(() => {
    return () => {
      Object.keys(localStorage).forEach((key) => {
        if (key.startsWith("questionnaire_")) {
          localStorage.removeItem(key);
        }
      });
    };
  }, []);

  useEffect(() => {
    if (visible) {
      setIsEnded(false);
    }
  }, [visible]);

  const handleOnClose = useCallback(() => {
    setIsEnded(true);
    setWebinarMeetData(null);
  }, []);

  return (
    <>
      <Modal
        visible={visible && !isEnded}
        animation="zoom"
        maskAnimation="fade"
        destroyOnClose={true}
        width="100vw"
        height="100vh"
        bodyStyle={{
          minHeight: "100vh",
          padding: 0,
          background: "black",
        }}
      >
        <WebinarMeetModalStyles>
          {loading && <ContentLoader />}
          <JitsiContainer>
            {visible && !loading && webinarMeetData && (
              <JitsyMeeting
                key={webinarId}
                jitsyData={webinarMeetData}
                close={handleOnClose}
              />
            )}
          </JitsiContainer>
        </WebinarMeetModalStyles>
      </Modal>

      {isEnded && (
        <EndMeetingQuestionnairesModal
          entityId={webinarId}
          entityModel={QuestionnaireModelType.WEBINAR}
          setIsEnded={() => {
            onClose();
            handleOnClose();
          }}
        />
      )}
    </>
  );
};

export default WebinarMeetModal;
