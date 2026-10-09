import React from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { RecursiveLessons } from "./_components/RecursiveLessons";
import {
  CourseAgendaContextProvider,
  CourseAgendaContextProviderProps,
  useCourseAgendaContext,
} from "./_components/context";
import styles from "./CourseAgenda.module.css";

type CourseAgendaProps = ExtendableStyledComponent &
  Omit<CourseAgendaContextProviderProps, "children">;

const CourseAgendaContent: React.FC<ExtendableStyledComponent> = ({
  className = "",
}) => {
  const { lessons } = useCourseAgendaContext();

  return (
    <section className={`${styles.root} ulams-component ${className}`}>
      <ul className="lessons__list">
        <RecursiveLessons lessons={lessons} />
      </ul>
    </section>
  );
};

export const CourseAgenda: React.FC<CourseAgendaProps> = ({
  className,
  ...contextProps
}) => (
  <CourseAgendaContextProvider {...contextProps}>
    <CourseAgendaContent className={className} />
  </CourseAgendaContextProvider>
);

export default CourseAgenda;
