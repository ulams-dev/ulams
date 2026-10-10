import { memo } from "react";
import { useTranslation } from "react-i18next";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import styles from "../styles.module.css";
import { useCourseRatingContext } from "../Provider";
import { CourseRatingsQuestionnairesContent } from "./Content";
import { CourseRatingsQuestionnairesDropdowns } from "./Dropdowns";

export const CourseRatingsQuestionnaires = memo(() => {
  const { questionnaires, courseId, questionId } = useCourseRatingContext();
  const { t } = useTranslation();

  return (
    <>
      <Title level={4} className={styles.title}>
        {t("CoursePage.Questionnaires")}
      </Title>
      {questionnaires.length > 0 ? (
        <>
          <CourseRatingsQuestionnairesDropdowns
            questionnaires={questionnaires}
          />
          {courseId && questionId && (
            <CourseRatingsQuestionnairesContent
              courseId={courseId}
              questionId={questionId}
            />
          )}
        </>
      ) : (
        <Text>{t("CoursePage.CourseRatingsEmpty")}</Text>
      )}
    </>
  );
});
