import * as React from "react";
import { PropsWithChildren } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./RatioBox.module.css";

export interface RatioBoxProps extends ExtendableStyledComponent {
  ratio: number;
  objectPosition?: React.CSSProperties["objectPosition"];
}

export const RatioBox: React.FC<PropsWithChildren<RatioBoxProps>> = ({
  children,
  ratio,
  objectPosition,
  className,
}) => {
  return (
    <div
      style={
        {
          "--ratio-padding": `${ratio * 100}%`,
          "--ratio-object-position": objectPosition || "center",
        } as React.CSSProperties
      }
      className={cx(styles.ratioBox, "ulams-component", className ?? "")}
    >
      {children}
    </div>
  );
};
