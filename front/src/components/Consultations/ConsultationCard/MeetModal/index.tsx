import { useCallback, useContext, useEffect, useState } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import { Modal } from "@ulams/components/components/atoms/Modal/Modal";
import { JitsyData } from "@ulams/sdk/types";
import ContentLoader from "@/components/_App/ContentLoader";
import JitsyMeeting from "@/components/Consultations/ConsultationCard/JitsyMeeting";
import { QuestionnaireModelType } from "@/types/questionnaire";
import { QuestionnairesModal } from "@/components/Courses/Course/CoursePanelLayout/FinishPage/Rate";
import { ConsultationModalContext } from "@/components/Consultations/ConsultationCard/Buttons/context";
import { EndMeetingQuestionnairesModal } from "@/components/Consultations/ConsultationCard/EndMeetingQuestionnaires";
import styles from "./styles.module.css";

interface Props {
  onClose: () => void;
}

const ConsultationMeetModal = ({ onClose }: Props) => {
  const [meetData, setMeetData] = useState<JitsyData | null>(null);
  const [isEnded, setIsEnded] = useState(false);
  const [loading, setLoading] = useState(false);
  const { generateConsultationJitsy } = useContext(UlamsContext);
  const consultationModalContext = useContext(ConsultationModalContext);

  useEffect(() => {
    const getMeetUrl = async () => {
      setLoading(true);
      if (consultationModalContext?.consultationData) {
        const res = await generateConsultationJitsy(
          consultationModalContext?.consultationData?.consultationTermId,
          consultationModalContext?.consultationData?.term
        );
        if (res.success) {
          setMeetData((res as { data: JitsyData }).data);
        }
        setLoading(false);
      }
    };

    getMeetUrl();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [consultationModalContext?.consultationData]);

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
    setIsEnded(false);
  }, [consultationModalContext?.consultationData]);

  const handleOnClose = useCallback(() => {
    setIsEnded(true);
    consultationModalContext?.setModalOpen?.(false);
    onClose();
  }, [setIsEnded, onClose, consultationModalContext]);

  return (
    <>
      <Modal
        visible={consultationModalContext?.isModalOpen}
        animation="zoom"
        maskAnimation="fade"
        width="100vw"
        height="100vh"
        bodyStyle={{
          minHeight: "100vh",
          padding: 0,
          background: "black",
        }}
      >
        <div className={styles.meetModal}>
          {loading && <ContentLoader />}
          <div className={styles.jitsiContainer}>
            {!loading && meetData && (
              <JitsyMeeting
                key={consultationModalContext?.consultationData?.consultationId}
                jitsyData={meetData}
                close={handleOnClose}
              />
            )}
          </div>
        </div>

        <QuestionnairesModal
          entityId={Number(
            consultationModalContext?.consultationData?.consultationId
          )}
          entityModel={QuestionnaireModelType.CONSULTATION}
        />
      </Modal>
      {isEnded && (
        <EndMeetingQuestionnairesModal
          entityId={Number(
            consultationModalContext?.consultationData?.consultationId
          )}
          entityModel={QuestionnaireModelType.CONSULTATION}
          setIsEnded={setIsEnded}
        />
      )}
    </>
  );
};

export default ConsultationMeetModal;
