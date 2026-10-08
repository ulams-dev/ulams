import RateCourse from "@/components/Courses/RateCourse";
import {
  getFormattedDifferenceRelativeToNow,
  relativeTimeFormatter,
} from "@/utils/index";

import { Button } from "@ulams/components/components/atoms/Button/Button";
import { Modal } from "@ulams/components/components/atoms/Modal/Modal";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { API } from "@ulams/sdk";
import { UlamsContext } from "@ulams/sdk/react";
import { CourseProgressItem } from "@ulams/sdk/types";
import {
  ButtonHTMLAttributes,
  FC,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import { useTranslation } from "react-i18next";

import { ResetProgressModal } from "../ResetProgressModal";
import { QuestionnaireModelType } from "@/types/questionnaire";
import styles from "./styles.module.css";
import { getQuestionnaires } from "@/utils/questionnaires";
import GetCertificate from "@/components/Profile/ProfileCourses/CourseCardActions/certificate";
import { IconRate } from "@/icons/index";
import ContentLoader from "@/components/_App/ContentLoader";

export const ActionButton: FC<ButtonHTMLAttributes<HTMLButtonElement>> = ({
  className,
  ...props
}) => (
  <button {...props} className={`${styles.actionButton} ${className ?? ""}`} />
);

interface Props {
  courseData: CourseProgressItem;
  courseProgress: number;
}

export const CourseCardActions: FC<Props> = ({
  courseData,
  courseProgress,
}) => {
  const [courseId, setCourseId] = useState<number | undefined>(undefined);
  const [showResetProgressModal, setShowResetProgressModal] = useState(false);
  const { fetchQuestionnaires, fetchQuestionnaire } = useContext(UlamsContext);
  const [state, setState] = useState({
    show: false,
    step: 0,
    loading: false,
  });

  const { t } = useTranslation();

  const status = {
    isDone: courseData.finish_date,
    isActive: courseData.start_date && !courseData.finish_date,
    isNotStarted: !courseData.start_date && !courseData.finish_date,
  };

  const deadlineDate = useMemo(
    () => (courseData.deadline ? new Date(courseData.deadline) : null),
    [courseData.deadline]
  );

  const isDeadlineMissed = deadlineDate
    ? deadlineDate.getTime() < Date.now()
    : false;

  const timeDifference = useMemo(
    () =>
      deadlineDate ? getFormattedDifferenceRelativeToNow(deadlineDate) : null,
    [deadlineDate]
  );

  const [questionnaires, setQuestionnaires] = useState<API.Questionnaire[]>([]);

  const handleClose = useCallback(() => {
    setCourseId(undefined);
    setState((prevState) => ({
      ...prevState,
      show: false,
    }));

    if (state.step < questionnaires.length - 1) {
      setState((prevState) => ({
        ...prevState,
        step: prevState.step + 1,
      }));

      const timer = setTimeout(() => {
        setState((prevState) => ({
          ...prevState,
          show: true,
        }));
      }, 500);
      return () => clearTimeout(timer);
    }
  }, [questionnaires, state.step]);

  useEffect(() => {
    courseId &&
      getQuestionnaires({
        courseId,
        fetchQuestionnaire,
        fetchQuestionnaires,
        onSucces: (items) => {
          setQuestionnaires(items);
        },
        onFinish: () =>
          setState((prevState) => ({
            ...prevState,
            loading: false,
          })),
      });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [courseId]);

  return (
    <div className={styles.wrapper}>
      {status.isDone && <GetCertificate courseId={courseData.course.id} />}
      {courseProgress === 100 && (
        <>
          <ActionButton
            onClick={() => {
              setCourseId(courseData.course.id);
              setState((prevState) => ({
                ...prevState,
                show: true,
                loading: true,
              }));
            }}
          >
            <IconRate /> {t<string>("MyProfilePage.RateCourse")}{" "}
            {state.loading && <ContentLoader width="10px" height="10px" />}
          </ActionButton>
          <div className={styles.resetCourseWrapper}>
            {!isDeadlineMissed && status.isDone && (
              <Button
                mode="secondary"
                onClick={() => setShowResetProgressModal(true)}
              >
                {t<string>("MyProfilePage.ResetCourseProgress")}
              </Button>
            )}
          </div>
        </>
      )}
      {!!isDeadlineMissed && timeDifference !== null && timeDifference[0] < 0 && (
        <Text size="12">
          {t<string>("MyProfilePage.AccessCourseExpired")}{" "}
          {relativeTimeFormatter.format(timeDifference[0], timeDifference[1])}
        </Text>
      )}
      <ResetProgressModal
        courseData={courseData}
        visible={showResetProgressModal}
        onClose={() => setShowResetProgressModal(false)}
      />
      {state.show &&
        courseId &&
        !state.loading &&
        (!!questionnaires.length ? (
          <>
            <RateCourse
              entityModel={QuestionnaireModelType.COURSE}
              entityId={courseId}
              visible={state.show}
              onClose={handleClose}
              questionnaire={questionnaires[state.step]}
            />
          </>
        ) : (
          <Modal
            onClose={handleClose}
            visible={state.show}
            animation="zoom"
            maskAnimation="fade"
            destroyOnClose={true}
            width={468}
          >
            <Title style={{ textAlign: "center" }}>
              {t<string>("CourseProgram.CourseRated")}
            </Title>
          </Modal>
        ))}
    </div>
  );
};
