import React, { useRef } from "react";
import { CSSTransition } from "react-transition-group";
import { Text, Title, Stack } from "../../..";
import styles from "./DefaultQuestionLayout.module.css";

type ResultScore = null | number;

type ResultScoreState = "positive" | "neutral" | "negative";

interface Props extends React.HTMLAttributes<HTMLDivElement> {
  title: string;
  question: string;
  children: React.ReactNode;
  showScore?: boolean;
  resultScore?: ResultScore;
}

function determineResultScoreState(score?: ResultScore): ResultScoreState {
  if (!score) return "neutral";
  else if (score > 0) return "positive";
  else if (score < 0) return "negative";

  return "neutral";
}

const resultScoreSign: Record<ResultScoreState, string> = {
  positive: "+",
  neutral: "",
  negative: "-",
};

const DefaultQuestionLayout: React.FC<Props> = ({
  title,
  question,
  children,
  showScore,
  resultScore,
  ...props
}) => {
  const resultScoreState = determineResultScoreState(resultScore);
  const scoreRef = useRef<HTMLSpanElement | null>(null);

  return (
    <Stack {...props} $gap={6}>
      <Stack $gap={2}>
        {title && <Title level="2">{title}</Title>}
        <Text className={styles.relativeText}>
          {question}
          <CSSTransition
            in={showScore}
            nodeRef={scoreRef}
            timeout={200}
            classNames="fade"
            unmountOnExit
          >
            <span
              ref={scoreRef}
              className={styles.scoreIndicator}
              data-score-state={resultScoreState}
            >
              {`${resultScoreSign[resultScoreState]}${resultScore ?? 0}`}
            </span>
          </CSSTransition>
        </Text>
      </Stack>
      <Stack className={styles.leftPaddingStack} $gap={4}>
        {children}
      </Stack>
    </Stack>
  );
};

export default DefaultQuestionLayout;
