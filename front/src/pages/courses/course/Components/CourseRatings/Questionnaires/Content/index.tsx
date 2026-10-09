import { useCourseAnswers } from "@/hooks/courses/useCourseAnswers";
import { Spin } from "@ulams/components/components/atoms/Spin/Spin";
import Pagination from "../../../../../../../components/Common/Pagination";
import { AnswerComponent } from "../../AnswerComponent";
import { Stack } from "@ulams/components/components/atoms/Stack/index";
import styles from "../../styles.module.css";

interface Props {
  courseId: number;
  questionId: number;
}

export const CourseRatingsQuestionnairesContent = ({
  courseId,
  questionId,
}: Props) => {
  const { answersMeta, loading, onPageChange, questionnaireAnswers } =
    useCourseAnswers({
      questionId,
      courseId,
    });

  return (
    <Stack className={styles.stack}>
      {loading ? (
        <Spin />
      ) : (
        <>
          {questionnaireAnswers &&
            questionnaireAnswers.map((question) => (
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
      )}
    </Stack>
  );
};
