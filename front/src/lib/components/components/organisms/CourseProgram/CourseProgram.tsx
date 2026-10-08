import React from "react";
import { useTranslation } from "react-i18next";
import styled, { withTheme } from "styled-components";
import type { API } from "@ulams/sdk";
import type { ExtendableStyledComponent } from "@ulams/components/types/component";
import { Text } from "../../../index";
import { RecursiveLessons } from "./_components/RecursiveLessons";
import { StyledSection } from "./_components/styles";
import type { SharedComponentProps } from "./_components/types";

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

  return (
    <StyledSection $mobile={mobile} className={`ulams-component ${className}`}>
      <Text>{t("Course.Agenda")}</Text>
      <ul className="lessons__list">
        <RecursiveLessons
          lessons={lessons}
          onTopicClick={onTopicClick}
          mobile={mobile}
        />
      </ul>
    </StyledSection>
  );
};

const NewComponent = styled(CourseProgram)<Props>``;

export default withTheme(NewComponent);
