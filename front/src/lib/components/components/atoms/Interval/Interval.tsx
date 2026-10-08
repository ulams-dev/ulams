import * as React from "react";
import { calcPercentage } from "../../../utils/utils";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./Interval.module.css";

export interface IntervalProps extends ExtendableStyledComponent {
  current: number;
  max: number;
}

export const Interval: React.FC<IntervalProps> = ({ current, max, className }) => {
  return (
    <div
      style={
        {
          "--interval-width": calcPercentage(current, max),
        } as React.CSSProperties
      }
      className={cx(styles.interval, "ulams-component", className)}
    >
      <div></div>
    </div>
  );
};
