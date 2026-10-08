import { OnboardingOption } from "@/components/Onboarding";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import React, { useCallback, useEffect } from "react";
import { isMobile } from "react-device-detect";
import { useTranslation } from "react-i18next";
import { Range, Direction } from "react-range";
import styles from "./Slide.module.css";

const STEP = 1;
const MIN = 1;

type Props = {
  options: OnboardingOption[];
  onAnswer: (answer: string) => void;
};

const SlideOption: React.FC<Props> = ({ options, onAnswer }) => {
  const [values, setValues] = React.useState([1]);
  const MAX = options.length;
  const { i18n } = useTranslation();

  const handleChange = useCallback(
    (values: number[]) => {
      setValues(values);
      onAnswer(`${options[values[0] - 1]?.value}`);
    },
    [onAnswer, options]
  );

  useEffect(() => {
    onAnswer(`${options[0]?.value}`);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className={styles.wrapper}>
      <Range
        step={STEP}
        min={MIN}
        max={MAX}
        direction={Direction.Down}
        values={values}
        onChange={(values) => handleChange(values)}
        rtl={false}
        renderMark={({ props, index }) => (
          <div {...props} className={styles.mark}>
            <div className={`line ${styles.line}`}></div>
            <div className={`content ${styles.content}`}>
              <Text size="14">{options[index].label[i18n.language]}</Text>
            </div>
          </div>
        )}
        renderTrack={({ props, children }) => (
          <div
            className={`${styles.track}${isMobile ? ` ${styles.mobile}` : ""}`}
            role="button"
            tabIndex={0}
            onMouseDown={props.onMouseDown}
            onTouchStart={props.onTouchStart}
            style={{
              ...props.style,
            }}
          >
            <div className={`track ${styles.trackLine}`} ref={props.ref}>
              {children}
            </div>
          </div>
        )}
        renderThumb={({ props }) => (
          <div
            {...props}
            className={styles.thumb}
            style={{
              ...props.style,
            }}
          />
        )}
      />
    </div>
  );
};

export default SlideOption;
