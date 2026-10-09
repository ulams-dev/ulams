import { useCoursePanel } from "@/components/Courses/Course/Context";
import { IconMenuSchedule } from "@/icons/index";
import { ProgressBar } from "@ulams/components";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { useTranslation } from "react-i18next";
import styles from "./styles.module.css";

interface Props {
  menuOnClick?: () => void;
}

export const Subheader = ({ menuOnClick }: Props) => {
  const {
    finishedTopicsIds,
    flatTopics,
    currentLessonParentsIds,
    flatLessons,
    currentTopic,
    currentLesson,
  } = useCoursePanel();
  const { t } = useTranslation();

  const parentLesson = flatLessons?.find(
    ({ id }) => id === currentLessonParentsIds?.at(0)
  );
  const progress =
    ((finishedTopicsIds ?? []).length / (flatTopics ?? []).length) * 100;

  return (
    <div className={styles.wrapper}>
      <div className={styles.progressBarContainer}>
        <ProgressBar
          currentProgress={progress}
          maxProgress={100}
          label={
            <Title className={styles.title} level={2}>
              <span>{parentLesson?.title || currentLesson?.title || ""}</span>{" "}
              {currentTopic?.title}
            </Title>
          }
          variant="square"
        />
      </div>
      {/* Mobile */}
      <button
        type="button"
        className={styles.iconWrapper}
        aria-label={t("CoursePanel.MenuButtonAria")}
        onClick={menuOnClick}
      >
        <IconMenuSchedule />
      </button>
    </div>
  );
};
