import { Button } from "@ulams/components/components/atoms/Button/Button";
import { Row } from "@ulams/components/components/atoms/Row/index";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { Modal } from "@ulams/components/components/atoms/Modal/Modal";

import { UlamsContext } from "@ulams/sdk/react";
import { CourseProgressItem } from "@ulams/sdk/types";
import { FC, useCallback, useContext } from "react";
import { useTranslation } from "react-i18next";
import { useHistory } from "react-router-dom";

interface Props {
  courseData: CourseProgressItem;
  visible: boolean;
  onClose: () => void;
}

export const ResetProgressModal: FC<Props> = ({
  courseData,
  visible,
  onClose,
}) => {
  const { sendProgress } = useContext(UlamsContext);
  const { t } = useTranslation();
  const { push } = useHistory();

  const handleResetProgress = useCallback(async () => {
    await sendProgress(
      courseData.course.id,
      courseData.progress.map(({ topic_id }) => ({ topic_id, status: 0 }))
    );

    push(`/course/${courseData.course.id}`);
  }, [courseData.course.id, courseData.progress, sendProgress, push]);

  return (
    <Modal
      animation="zoom"
      maskAnimation="fade"
      destroyOnClose={true}
      visible={visible}
      onClose={onClose}
    >
      <>
        <Title level={4}>{t("ResetProgressModal.Continue")}</Title>
        <Text> {t("ResetProgressModal.RestartCourse")}</Text>
        <Row $gap={16}>
          <Button mode="primary" onClick={handleResetProgress}>
            {t("ResetProgressModal.WantContinue")}
          </Button>
          <Button mode="primary" onClick={onClose}>
            {t("ResetProgressModal.Cancel")}
          </Button>
        </Row>
      </>
    </Modal>
  );
};
