import type * as React from "react";
import type { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./Tag.module.css";
import { legacyDefault } from "../../../utils/legacy";

export interface LinkProps
  extends React.ButtonHTMLAttributes<HTMLSpanElement>,
    ExtendableStyledComponent {
  children?: React.ReactNode;
}

export const Tag: React.FC<LinkProps> = (props) => {
  const isButton = typeof props.onClick === "function";
  return (
    <span
      {...props}
      className={cx(
        styles.tag,
        isButton && styles.button,
        "ulams-component",
        props.className ?? ""
      )}
    >
      {props.children}
    </span>
  );
};

export default legacyDefault(Tag);
