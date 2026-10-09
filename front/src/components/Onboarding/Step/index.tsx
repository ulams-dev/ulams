import {
  OnboardingOption,
  OnboardingStep,
  OnboardingStepType,
} from "@/components/Onboarding";
import SlideOption from "@/components/Onboarding/Step/slide";
import ResponsiveImage from "@ulams/components/components/organisms/ResponsiveImage/ResponsiveImage";
import { Radio, Text, Title } from "@ulams/components";
import { UlamsContext } from "@ulams/sdk/react";
import { useCallback, useContext } from "react";
import { useTranslation } from "react-i18next";
import styles from "./Step.module.css";

type Props = {
  step: OnboardingStep;
  onAnswer: (answer: string) => void;
  answers?: {
    [key: string]: string;
  };
};

const Step: React.FC<Props> = ({ step, onAnswer, answers }) => {
  const { settings } = useContext(UlamsContext);
  const { i18n } = useTranslation();

  const getImage = useCallback(() => {
    if (step.image) {
      return settings.value.onboarding[step.image];
    } else {
      return null;
    }
  }, [step, settings.value.onboarding]);

  const renderProperOptions = useCallback(
    (option: OnboardingOption) => {
      switch (step.type) {
        case OnboardingStepType.radio:
          return (
            <Radio
              value={option.value}
              label={option.label[i18n.language]}
              name={step.data}
              onChange={(e) => onAnswer(e.target.value)}
            />
          );

        case OnboardingStepType.options:
          return (
            <button
              className={styles.buttonOption}
              data-active={
                (answers && answers[step.data] === option.value) || false
              }
              onClick={() => onAnswer(option.value)}
            >
              {option.label[i18n.language]}
            </button>
          );

        default:
          return (
            <Radio
              value={option.value}
              label={option.label[i18n.language]}
              name={step.data}
              onChange={(e) => onAnswer(e.target.value)}
            />
          );
      }
    },
    [i18n.language, step.type, step.data, onAnswer, answers]
  );

  return (
    <div className={styles.step}>
      {step.image && getImage() && (
        <div className="step-image">
          <ResponsiveImage path={getImage()} srcSizes={[500, 750, 1000]} />
        </div>
      )}
      {step.hint && (
        <div className={styles.hint}>
          <Title level={4}>{step.hint.title[i18n.language]}</Title>
          <Text size="13">{step.hint.text[i18n.language]}</Text>
        </div>
      )}
      <Title level={4}>{step.question[i18n.language]}</Title>
      <div
        className={`options ${styles.options} ${
          step.type === OnboardingStepType.options
            ? `buttons ${styles.buttons}`
            : ""
        }`}
      >
        {step.type === OnboardingStepType.slide && (
          <SlideOption options={step.options} onAnswer={onAnswer} />
        )}
        {step.type !== OnboardingStepType.slide &&
          step.options.map((option, index) => (
            <div key={option.value + index} className={`option ${styles.option}`}>
              {renderProperOptions(option)}
            </div>
          ))}
      </div>
    </div>
  );
};
export default Step;
