import { useState } from "react";
import { CourseSchedule } from "@/components/Courses/Course/CoursePanelLayout/Schedule";
import { Subheader } from "@/components/Courses/Course/CoursePanelLayout/Subheader";
import { TopicView } from "@/components/Courses/Course/CoursePanelLayout/TopicView";
import { ButtonsNav } from "@/components/Courses/Course/CoursePanelLayout/ButtonsNav";
import styles from "./styles.module.css";

export const CoursePanelLayoutContent = () => {
  const [isScheduleOpen, setIsScheduleOpen] = useState(false);

  return (
    <div className={styles.wrapper}>
      <div className={styles.leftColumn}>
        <Subheader menuOnClick={() => setIsScheduleOpen((prev) => !prev)} />
        <TopicView />
        <ButtonsNav />
      </div>
      <div
        className={`${styles.rightColumn} ${isScheduleOpen ? styles.open : ""}`}
      >
        <CourseSchedule />
      </div>
    </div>
  );
};
