import AIChat from "@/components/Chat";
import { useCoursePanel } from "@/components/Courses/Course/Context";
import { CoursePanelLayoutContent } from "@/components/Courses/Course/CoursePanelLayout/Content";
import { CoursePanelFinishPage } from "@/components/Courses/Course/CoursePanelLayout/FinishPage";
import { CoursePanelHeader } from "@/components/Courses/Course/CoursePanelLayout/Header";
import styles from "./styles.module.css";

export const CoursePanelLayout = () => {
  const { showFinish, currentLesson } = useCoursePanel();

  return (
    <div className={styles.layoutWrapper}>
      <CoursePanelHeader />
      {!showFinish && <CoursePanelLayoutContent />}
      {showFinish && <CoursePanelFinishPage />}
      {currentLesson?.id && <AIChat lessonID={currentLesson?.id} />}
    </div>
  );
};
