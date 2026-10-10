import React from "react";
import { useTranslation } from "react-i18next";
import { API } from "@ulams/sdk";
import { Input } from "../../../..";
import { getUniqueId } from "../../../../utils/utils";
import DefaultQuestionLayout from "../DefaultQuestionLayout";

interface Props extends API.QuizQuestion_ShortAnswers {
  onChange: React.ChangeEventHandler<HTMLInputElement>;
  onBlur: React.FocusEventHandler<HTMLInputElement>;
  value: string;
  hasQuizEnded?: boolean;
  resultScore?: number | null;
}

const ShortAnswers: React.FC<Props> = ({
  question,
  title,
  id,
  onChange,
  onBlur,
  value,
  hasQuizEnded,
  resultScore,
}) => {
  const { t } = useTranslation();

  return (
    <DefaultQuestionLayout
      data-testid={`short-answers-${question}`}
      title={title}
      question={question}
      resultScore={resultScore}
      showScore={hasQuizEnded}
    >
      <Input
        placeholder={t("Quiz.TypeAnswer")}
        id={getUniqueId(`ShortAnswers-${id}`)}
        name={`${id}`}
        disabled={hasQuizEnded}
        value={value}
        onChange={onChange}
        onBlur={onBlur}
      />
    </DefaultQuestionLayout>
  );
};

export default ShortAnswers;
