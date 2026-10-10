import React, { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { Row, Text } from "../../";
import styles from "./GiftQuizScore.module.css";

export type QuizScoreColor = "systemDanger" | "systemPositive";

interface Props {
  result?: number;
  max?: number;
}

const GiftQuizScore: React.FC<Props> = ({ result, max }) => {
  const { t } = useTranslation();
  const percentage = useMemo(() => {
    const percentageRes = (result ?? 0) / (max ?? 0);
    return Number.isNaN(percentageRes) ? 0 : percentageRes;
  }, [max, result]);

  return (
    <Row
      className={styles.wrapper}
      data-testid="gift-quiz-score"
      $gap={12}
      $alignItems="center"
    >
      <Row $gap={6} $alignItems="center">
        <Text family="secondary" size="xs" weight="bold">
          {t("Quiz.YourScore")}
        </Text>
      </Row>
      <Row $gap={8} $alignItems="center">
        <span
          className={styles.progress}
          style={
            {
              "--progress-width": `${percentage * 52}px`,
            } as React.CSSProperties
          }
        />
        <Text family="secondary" size="xs" weight="bold">
          {result}/{max}
        </Text>
      </Row>
    </Row>
  );
};

export default GiftQuizScore;
