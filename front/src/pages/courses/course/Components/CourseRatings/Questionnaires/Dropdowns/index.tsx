import { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import { Dropdown } from "@ulams/components/components/molecules/Dropdown/Dropdown";
import { useCourseRatingContext } from "../../Provider";
import { API } from "@ulams/sdk";
import { QuestionType } from "@/types/questionnaire";
import { Stack } from "@ulams/components/components/atoms/Stack/index";
import styles from "../../styles.module.css";

interface Props {
  questionnaires: API.Questionnaire[];
}

export const CourseRatingsQuestionnairesDropdowns = ({
  questionnaires,
}: Props) => {
  const { questionnaireId, setQuestionnaireId, questionId, setQuestionId } =
    useCourseRatingContext();
  const { t } = useTranslation();
  // Dropdown computes text contrast from this value in JS, so it needs the raw colour.
  const theme = useThemeTokens();

  const questionnairesFilter = useMemo(
    () =>
      questionnaires.map((item) => ({
        label: item.title,
        value: String(item.id),
      })),
    [questionnaires]
  );

  const questionnaireQuestionFilter = useMemo(
    () =>
      questionnaires
        ?.find((element) => element.id === questionnaireId)
        ?.questions.filter((item) => item.public_answers)
        .filter((item) => item.type !== QuestionType.REVIEW)
        .map((item) => ({
          label: item.title,
          value: String(item.id),
        })) || [],
    [questionnaireId, questionnaires]
  );

  return (
    <Stack className={styles.stack}>
      {questionnairesFilter.length > 1 && (
        <Dropdown
          onChange={(e) => setQuestionnaireId(Number(e.value))}
          options={questionnairesFilter}
          placeholder={t("CoursePage.SelectQuestionnaire")}
          backgroundColor={theme?.white}
          value={questionnairesFilter?.find(
            ({ value }) => value === String(questionnaireId)
          )}
        />
      )}
      <Dropdown
        onChange={(e) => setQuestionId(Number(e.value))}
        options={questionnaireQuestionFilter}
        placeholder={t("CoursePage.SelectQuestion")}
        backgroundColor={theme?.white}
        value={questionnaireQuestionFilter?.find(
          ({ value }) => value === String(questionId)
        )}
      />
    </Stack>
  );
};
