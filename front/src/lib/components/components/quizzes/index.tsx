import React, {
  useCallback,
  useContext,
  useMemo,
  useRef,
  useState,
} from "react";
import { useTranslation } from "react-i18next";
import { API } from "@ulams/sdk/index";
import {
  quizAttempt as fetchQuizAttempt,
  quizAnswer,
  quizAttemptFinish,
} from "@ulams/sdk/services/gfit_quiz";
import { UlamsContext } from "@ulams/sdk/react";
import { GiftQuizAnswer } from "@ulams/components/types/gift-quiz";
import { Button, Spin } from "../../";
import GiftQuizPlayerContent from "./GiftQuizPlayerContent";
import styles from "./index.module.css";

interface Props {
  topic: API.TopicQuiz;
  onTopicEnd?: () => void;
  className?: string;
}

interface QuizData {
  loading: boolean;
  value?: API.QuizAttempt & {
    is_ended?: boolean;
  };
  error?: API.DefaultResponseError;
}

function useQuiz(quizId: number | undefined, onTopicEnd?: () => void) {
  const [data, setData] = useState<QuizData>({ loading: false });
  const { token, apiUrl } = useContext(UlamsContext);

  const topicEndCb = useRef(onTopicEnd);

  const startQuiz = useCallback(() => {
    if (quizId && token) {
      setData((prev) => ({ ...prev, loading: true }));
      fetchQuizAttempt(apiUrl, token, {
        topic_gift_quiz_id: quizId,
      } as API.QuizAttempt)
        .then((response) => {
          if (response.success) {
            setData((prev) => ({ ...prev, value: response.data }));
          } else {
            setData((prev) => ({ ...prev, error: response }));
          }
        })
        .catch((error: API.DefaultResponseError) => {
          setData((prev) => ({ ...prev, error }));
        })
        .finally(() => {
          setData((prev) => ({ ...prev, loading: false }));
        });
    }
  }, [quizId, apiUrl, token]);

  const endQuiz = useCallback(
    (quizAttemptId: number) => {
      if (token) {
        quizAttemptFinish(apiUrl, token, quizAttemptId).then((response) => {
          if (response.success) {
            setData((prev) => ({ ...prev, value: response.data }));
            topicEndCb.current?.();
          }
        });
      }
    },
    [token, apiUrl]
  );

  const sendAnswer = useCallback(
    <Answer extends GiftQuizAnswer>(questionId: number, answer: Answer) => {
      if (data?.value?.id && token && !data?.value?.is_ended) {
        quizAnswer(apiUrl, token, {
          topic_gift_question_id: questionId,
          topic_gift_quiz_attempt_id: data.value.id,
          answer,
        });
      }
    },
    [token, apiUrl, data?.value?.id, data?.value?.is_ended]
  );

  const getQuestionAnswerObj = useCallback(
    (questionId: number) =>
      data.value?.answers?.find(
        (answerItem) => answerItem?.topic_gift_question_id === questionId
      ),
    [data.value?.answers]
  );

  return useMemo(
    () => ({
      data,
      startQuiz,
      sendAnswer,
      getQuestionAnswerObj,
      endQuiz,
    }),
    [data, sendAnswer, getQuestionAnswerObj, startQuiz, endQuiz]
  );
}

const GiftQuizPlayer: React.FC<Props> = ({ topic, className, onTopicEnd }) => {
  const { t } = useTranslation();
  const { data, startQuiz, sendAnswer, endQuiz } = useQuiz(
    topic.topicable.id,
    onTopicEnd
  );

  return (
    <div
      data-testid="gift-quiz-player"
      className={className ? `${styles.wrapper} ${className}` : styles.wrapper}
    >
      {!data.value && !data.loading && (
        <div className={styles.startButtonWrapper}>
          <Button mode="secondary" type="button" onClick={startQuiz}>
            {t<string>("Quiz.Start")}
          </Button>
        </div>
      )}
      {data.loading && !data.value && <Spin />}
      {data.value && (
        <GiftQuizPlayerContent
          attempt={data.value}
          startQuiz={startQuiz}
          sendAnswer={sendAnswer}
          endQuiz={endQuiz}
        />
      )}
    </div>
  );
};

export default GiftQuizPlayer;
