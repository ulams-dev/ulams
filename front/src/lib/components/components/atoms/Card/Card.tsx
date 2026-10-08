import * as React from "react";
import { PropsWithChildren } from "react";

import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./Card.module.css";

export interface CardProps extends ExtendableStyledComponent {
  // size of wings for a card
  wings?: "small" | "large" | "hidden";
  // overwrite default css style
  style?: React.CSSProperties;
  // block or inline
  inline?: boolean;
}

export const Card: React.FC<PropsWithChildren<CardProps>> = ({
  wings,
  children,
  style,
  inline,
  className = "",
}) => {
  return (
    <div
      style={style}
      className={cx(
        styles.card,
        inline && styles.inline,
        wings === "large" && styles.wingsLarge,
        wings === "small" && styles.wingsSmall,
        wings === "hidden" && styles.wingsHidden,
        "ulams-component",
        className
      )}
    >
      <div className="content">{children}</div>
    </div>
  );
};
