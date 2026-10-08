import * as React from "react";
import { useCallback } from "react";
import { useTranslation } from "react-i18next";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { calcPercentage } from "../../../utils/utils";
import { cx } from "../../../utils/cx";
import styles from "./ProgressBar.module.css";

const VariantTypes = {
  ROUNDED: "rounded",
  SQUARE: "square",
} as const;

type VariantTypeProp = typeof VariantTypes[keyof typeof VariantTypes];

export interface ProgressBarProps
  extends React.HTMLAttributes<HTMLDivElement>,
    ExtendableStyledComponent {
  variant?: VariantTypeProp;
  hideLabel?: boolean;
  label?: string | React.ReactNode;
  currentProgress: number;
  maxProgress: number;
}

export const ProgressBar: React.FC<ProgressBarProps> = (props) => {
  const { t } = useTranslation();
  const {
    variant = VariantTypes.ROUNDED,
    currentProgress,
    maxProgress,
    hideLabel,
    label = t("ProgressBar.defaultLabel"),
    className = "",
    ...divProps
  } = props;

  const renderLabel = useCallback(() => {
    if (hideLabel) {
      return <></>;
    }
    return <div className="label-value">{label}</div>;
  }, [hideLabel, label]);

  const percentageValue = useCallback(
    () => calcPercentage(currentProgress, maxProgress),
    [currentProgress, maxProgress]
  );

  return (
    <div
      {...divProps}
      className={cx(
        styles.progressBar,
        variant === VariantTypes.SQUARE && styles.square,
        variant === VariantTypes.ROUNDED && styles.rounded,
        "ulams-component",
        "lms-progress-bar",
        className
      )}
    >
      <div className="label-container">
        {renderLabel()}
        <span className="percentage-value">{percentageValue()}</span>
      </div>
      <div className="progress-container">
        <span className="progress-bars">
          <span className="empty"></span>
          <span className="filled" style={{ width: percentageValue() }}></span>
        </span>
      </div>
    </div>
  );
};
