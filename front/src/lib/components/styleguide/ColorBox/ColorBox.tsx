import * as React from "react";

import { cx } from "../../utils/cx";
import styles from "./ColorBox.module.css";

export const ColorBox: React.FC<{
  children?: React.ReactNode;
  mode: "primary" | "secondary";
}> = ({ children, mode = "primary" }) => {
  return (
    <div
      className={cx(
        styles.colorBox,
        mode === "primary" && styles.primary,
        mode === "secondary" && styles.secondary
      )}
    >
      <span className={`button`}>{children}</span>
    </div>
  );
};

/** Former default export: the bare styled box (no inner span), accepting div props. */
const ColorBoxDiv: React.FC<
  React.HTMLAttributes<HTMLDivElement> & { mode: string }
> = ({ mode, className, ...props }) => (
  <div
    {...props}
    className={cx(
      styles.colorBox,
      mode === "primary" && styles.primary,
      mode === "secondary" && styles.secondary,
      className
    )}
  />
);

export default ColorBoxDiv;
