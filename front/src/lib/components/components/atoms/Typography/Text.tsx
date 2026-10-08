import * as React from "react";

import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./Text.module.css";
import { legacyDefault } from "../../../utils/legacy";

const textSizes = ["24", "18", "16", "14", "13", "12", "11"] as const;
export type TextSize = typeof textSizes[number];

export interface TextProps
  extends React.HTMLAttributes<HTMLParagraphElement>,
    ExtendableStyledComponent {
  noMargin?: boolean;
  bold?: boolean;
  size?: TextSize;
  type?: "primary" | "secondary" | "warning" | "danger";
  className?: string;
}

export const Text: React.FC<TextProps> = ({
  children,
  noMargin,
  style,
  bold,
  size = "16",
  type = "primary",
  className = "",
  ...props
}) => {
  return (
    <p
      {...props}
      style={{ "--text-size": `${size}px`, ...style } as React.CSSProperties}
      className={cx(
        styles.text,
        noMargin && styles.noMargin,
        bold && styles.bold,
        type === "danger" && styles.danger,
        "ulams-component",
        className
      )}
    >
      {children}
    </p>
  );
};

export default legacyDefault(Text);
