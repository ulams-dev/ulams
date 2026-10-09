import React, { useCallback, useEffect, useRef, useState } from "react";
import { API } from "@ulams/sdk";
import { Row } from "../../../../";
import { GiftQuizMatchingAnswer } from "@ulams/components/types/gift-quiz";
import { BezierLine } from "../../../../utils/bezierLine";
import DefaultQuestionLayout from "../DefaultQuestionLayout";
import styles from "./Matching.module.css";

const getDefaultValues = (
  questions: string[],
  values: Record<string, string>
): ConnectedOptions[] =>
  (questions ?? [])?.map((name) => ({
    startOption: { name, x: 0, y: 0 },
    endOption: values[name] ? { name: values[name], x: 0, y: 0 } : null,
  }));

const createStartOptionObj = (
  elArr: HTMLElement[]
): Record<string, SelectedOption> =>
  elArr.reduce(
    (acc, el) => ({
      ...acc,
      [el.id]: {
        name: el.id,
        x: el?.offsetWidth,
        y: el?.offsetTop + el?.offsetHeight / 2,
      },
    }),
    {}
  );

const createEndOptionObj = (
  elArr: HTMLElement[],
  container: HTMLDivElement
): Record<string, SelectedOption> =>
  elArr.reduce(
    (acc, el) => ({
      ...acc,
      [el.id]: {
        name: el.id,
        x: container.offsetWidth - el.offsetWidth,
        y: el.offsetTop + el.offsetHeight / 2,
      },
    }),
    {}
  );

interface SelectedOption {
  name: string;
  x: number;
  y: number;
}

interface ConnectedOptions {
  startOption: SelectedOption;
  endOption?: SelectedOption | null;
}

interface Props extends API.QuizQuestion_Matching {
  onChange: (values: GiftQuizMatchingAnswer) => void;
  values: Record<string, string>;
  resultScore?: number | null;
  hasQuizEnded?: boolean;
}

const Matching: React.FC<Props> = ({
  question,
  options,
  onChange,
  title,
  values,
  hasQuizEnded,
  resultScore,
}) => {
  const [selectedOption, setSelectedOption] = useState<SelectedOption | null>(
    null
  );
  const [connectedOptions, setConnectedOptions] = useState<ConnectedOptions[]>(
    getDefaultValues(options?.sub_questions, values)
  );

  const refContainer = useRef<HTMLDivElement>(null);
  const startOptionsListRef = useRef<HTMLUListElement>(null);
  const endOptionsListRef = useRef<HTMLUListElement>(null);

  const handleSelectOptionFactory = useCallback(
    (name: string) => (e: React.MouseEvent<HTMLLIElement, MouseEvent>) => {
      if (!hasQuizEnded) {
        const startOption = {
          name,
          x: e.currentTarget.offsetWidth,
          y: e.currentTarget.offsetTop + e.currentTarget.offsetHeight / 2,
        };

        setSelectedOption(startOption);

        setConnectedOptions((prev) =>
          prev.map((connectedOptions) =>
            connectedOptions.startOption.name === name
              ? { startOption, endOption: connectedOptions.endOption }
              : connectedOptions
          )
        );
      }
    },
    [hasQuizEnded]
  );

  const selectHandlerFactory = useCallback(
    (name: string) => {
      return (e: React.MouseEvent<HTMLLIElement, MouseEvent>) => {
        if (refContainer.current && !hasQuizEnded) {
          const selectedEndOption = {
            name,
            x: refContainer.current.offsetWidth - e.currentTarget.offsetWidth,
            y: e.currentTarget.offsetTop + e.currentTarget.offsetHeight / 2,
          };
          setConnectedOptions((prev) =>
            prev.map(({ startOption, endOption }) =>
              startOption.name === selectedOption?.name
                ? {
                    startOption,
                    endOption: selectedEndOption,
                  }
                : { startOption, endOption }
            )
          );

          setSelectedOption(null);
        }
      };
    },
    [hasQuizEnded, selectedOption?.name]
  );

  const redrawLines = useCallback(() => {
    if (
      startOptionsListRef.current &&
      endOptionsListRef.current &&
      refContainer.current
    ) {
      const startOptionsElsArr = Array.from(
        startOptionsListRef.current.children
      ) as HTMLElement[];
      const endOptionsElsArr = Array.from(
        endOptionsListRef.current.children
      ) as HTMLElement[];

      const startOptionObj = createStartOptionObj(startOptionsElsArr);
      const endOptionObj = createEndOptionObj(
        endOptionsElsArr,
        refContainer.current
      );

      setConnectedOptions((prev) =>
        prev.map(({ startOption, endOption }) => ({
          startOption: startOptionObj[startOption.name],
          endOption: endOption?.name ? endOptionObj[endOption.name] : endOption,
        }))
      );
    }
  }, []);

  useEffect(() => {
    onChange({
      matching: connectedOptions.reduce<Record<string, string>>(
        (acc, curr) => ({
          ...acc,
          [curr.startOption.name]: curr.endOption?.name ?? "",
        }),
        {}
      ),
    });
  }, [connectedOptions]);

  useEffect(() => {
    const isStateReset = Object.values(values).length === 0;

    if (isStateReset) {
      setConnectedOptions(getDefaultValues(options.sub_questions, values));
    }
  }, [options, values]);

  useEffect(() => {
    redrawLines();
  }, [redrawLines]);

  return (
    <DefaultQuestionLayout
      data-testid={`matching-${question}`}
      title={title}
      question={question}
      resultScore={resultScore}
      showScore={hasQuizEnded}
    >
      <div className={styles.matcherWrapper} ref={refContainer}>
        <Row className={styles.row} $justifyContent="space-between">
          <ul ref={startOptionsListRef}>
            {options?.sub_questions.map((name) => (
              <li
                key={name}
                className={`${styles.optionListItem} ${name}`}
                id={name}
                data-disabled={!!hasQuizEnded}
                data-checked={selectedOption?.name === name}
                onClick={handleSelectOptionFactory(name)}
              >
                <div
                  className={`${styles.circle} ${styles.startCircle}`}
                  data-checked={selectedOption?.name === name}
                />
                {name}
              </li>
            ))}
          </ul>
          {connectedOptions.map(
            (item, index) =>
              item.endOption && (
                <BezierLine
                  key={index}
                  x1={item.startOption.x}
                  x2={item.endOption.x}
                  y1={item.startOption.y}
                  y2={item.endOption.y}
                />
              )
          )}
          <ul ref={endOptionsListRef}>
            {options?.sub_answers.map((name) => (
              <li
                key={name}
                id={name}
                className={`${styles.optionListItem} ${name}`}
                data-disabled={!!hasQuizEnded}
                onClick={selectHandlerFactory(name)}
              >
                <div className={`${styles.circle} ${styles.endCircle}`} />
                {name}
              </li>
            ))}
          </ul>
        </Row>
      </div>
    </DefaultQuestionLayout>
  );
};

export default Matching;
