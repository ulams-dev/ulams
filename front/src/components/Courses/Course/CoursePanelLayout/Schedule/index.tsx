import { useContext } from "react";
import { useTranslation } from "react-i18next";
import { useHistory } from "react-router-dom";
import { API } from "@ulams/sdk";
import { isMobile } from "react-device-detect";
import { UlamsContext } from "@ulams/sdk/react";
import { CourseAgenda } from "@ulams/components/components/organisms/CourseAgenda/CourseAgenda";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { useCoursePanel } from "@/components/Courses/Course/Context";
import subheaderStyles from "../Subheader/styles.module.css";
import styles from "./styles.module.css";

export const CourseSchedule = () => {
  const { t } = useTranslation();
  const { user } = useContext(UlamsContext);
  const {
    currentTopic,
    finishedTopicsIds,
    availableTopicsIds,
    courseId,
    currentCourseProgram,
  } = useCoursePanel();
  const history = useHistory();

  return (
    <div className={styles.wrapper}>
      <div className={styles.title}>
        <Title className={subheaderStyles.title} level={2}>
          {t("CoursePanel.ScheduleTitle")}
        </Title>
      </div>
      <div className={styles.content}>
        <CourseAgenda
          areAllTopicsUnlocked={
            !!currentCourseProgram?.authors.find(
              ({ id }) => id === user.value?.id
            )
          }
          lessons={currentCourseProgram?.lessons || []}
          currentTopicId={Number(currentTopic?.id)}
          finishedTopicIds={finishedTopicsIds ?? []}
          onMarkFinished={() => console.log("onMarkFinished")}
          onTopicClick={(topic: API.Topic) => {
            history.push(`/course/${courseId}/${topic.lesson_id}/${topic.id}`);
          }}
          onCourseFinished={() => console.log("onCourseFinished")}
          availableTopicsIds={availableTopicsIds ?? []}
          isMobile={isMobile}
        />
      </div>
    </div>
  );
};
