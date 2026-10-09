import React from "react";
import { useTranslation } from "react-i18next";
import type { API } from "@ulams/sdk";
import type { ExtendableStyledComponent } from "@ulams/components/types/component";
import { Text } from "../../../index";
import { useThemeTokens } from "../../../theme/applyTheme";
import { RecursiveLessons } from "./_components/RecursiveLessons";
import type { SharedComponentProps } from "./_components/types";
import styles from "./CourseProgram.module.css";

interface Props extends SharedComponentProps, ExtendableStyledComponent {
  lessons: API.Lesson[];
}

export const CourseProgram: React.FC<Props> = ({
  lessons,
  onTopicClick,
  mobile = false,
  className = "",
}) => {
  const { t } = useTranslation();
  const theme = useThemeTokens();
  // Topic numbers are fully opaque only when the theme sets a numerations colour.
  const hasNumerations = !!(
    theme?.dm__numerationsColor || theme?.numerationsColor
  );

  return (
    <section
      data-mobile={mobile}
      data-numerations={hasNumerations}
      className={`${styles.root} ulams-component ${className}`}
    >
      <Text>{t("Course.Agenda")}</Text>
      <ul className="lessons__list">
        <RecursiveLessons
          lessons={lessons}
          onTopicClick={onTopicClick}
          mobile={mobile}
        />
      </ul>
    </section>
  );
};

export default CourseProgram;
