import { useCoursePanel } from "@/components/Courses/Course/Context";
import CourseProgramContent from "@/components/Courses/Course/CourseProgramContent";
import ContentLoader from "@/components/_App/ContentLoader";
import styles from "./styles.module.css";

export const TopicView = () => {
  const { currentTopic, isAnyDataLoading } = useCoursePanel();

  return (
    <main className={styles.wrapper}>
      <CourseProgramContent topic={currentTopic} />
      {isAnyDataLoading && <ContentLoader />}
    </main>
  );
};
