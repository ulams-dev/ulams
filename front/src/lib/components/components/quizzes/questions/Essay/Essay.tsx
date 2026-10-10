import React from "react";
import { useTranslation } from "react-i18next";
import { API } from "@ulams/sdk";
import { TextArea } from "../../../../";
import { getUniqueId } from "../../../../utils/utils";
import DefaultQuestionLayout from "../DefaultQuestionLayout";

interface Props extends API.QuizQuestion_Essay {
  onChange: React.ChangeEventHandler<HTMLTextAreaElement>;
  onBlur: React.FocusEventHandler<HTMLTextAreaElement>;
  value: string;
  hasQuizEnded?: boolean;
  resultScore?: number | null;
}

const Essay: React.FC<Props> = ({
  id,
  title,
  question,
  onChange,
  onBlur,
  value,
  hasQuizEnded,
  resultScore,
}) => {
  const { t } = useTranslation();
  return (
    <DefaultQuestionLayout
      data-testid={`essay-${question}`}
      title={title}
      question={question}
      resultScore={resultScore}
      showScore={hasQuizEnded}
    >
      <TextArea
        placeholder={t("Quiz.TypeAnswer")}
        name={`${id}`}
        disabled={hasQuizEnded}
        id={getUniqueId(`Essay-${id}`)}
        value={value}
        onChange={onChange}
        onBlur={onBlur}
      />
    </DefaultQuestionLayout>
  );
};

export default Essay;
