import { useCourseAnswers } from "@/hooks/courses/useCourseAnswers";
import { Spin } from "@ulams/components/components/atoms/Spin/Spin";
import Pagination from "@/components/Common/Pagination";
import { AnswerComponent } from "../../AnswerComponent";
import { Stack } from "@ulams/components/components/atoms/Stack/index";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import styles from "../../styles.module.css";
import { useTranslation } from "react-i18next";

interface Props {
  courseId: number;
  questionId: number;
}

export const CourseRatingsReviewsContent = ({
  courseId,
  questionId,
}: Props) => {
  const { answersMeta, loading, onPageChange, questionnaireAnswers } =
    useCourseAnswers({
      questionId,
      courseId,
    });
  const { t } = useTranslation();

  return (
    <Stack className={styles.stack}>
      {loading ? (
        <Spin />
      ) : (questionnaireAnswers || [])?.length > 0 ? (
        <>
          <Title level={4} className={styles.title}>
            {t("CoursePage.CourseRatingsTitle")}
          </Title>
          {(questionnaireAnswers || []).map((question) => (
            <AnswerComponent question={question} />
          ))}
          {answersMeta.total > answersMeta.per_page && (
            <div className={styles.paginationContainer}>
              <Pagination
                total={answersMeta.total}
                perPage={answersMeta.per_page}
                currentPage={answersMeta.current_page}
                onPage={onPageChange}
              />
            </div>
          )}
        </>
      ) : null}
    </Stack>
  );
};
