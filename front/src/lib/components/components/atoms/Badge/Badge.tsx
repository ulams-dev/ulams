import * as React from "react";
import { PropsWithChildren } from "react";

import chroma from "chroma-js";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { useThemeTokens } from "../../../theme/applyTheme";
import orangeTheme from "../../../theme/orange";
import { cx } from "../../../utils/cx";
import styles from "./Badge.module.css";
import { legacyDefault } from "../../../utils/legacy";

export interface BadgeProps
  extends React.HTMLAttributes<HTMLDivElement>,
    ExtendableStyledComponent {
  children?: React.ReactNode;
  color?: string;
  lightContrast?: boolean;
}

export const Badge: React.FC<PropsWithChildren<BadgeProps>> = ({
  children,
  color,
  className = "",
  style,
  lightContrast,
  ...props
}) => {
  const tokens = useThemeTokens();
  const base = color || tokens?.primaryColor || orangeTheme.primaryColor;

  const cts = React.useMemo(() => {
    try {
      return chroma.contrast("#fff", base) >= 2.5;
    } catch {
      return false;
    }
  }, [base]);

  return (
    <div
      {...props}
      style={
        (color
          ? { "--badge-bg": color, ...style }
          : style) as React.CSSProperties
      }
      className={cx(
        styles.badge,
        (lightContrast ?? cts) && styles.lightContrast,
        "ulams-component",
        className
      )}
    >
      {children}
    </div>
  );
};

export default legacyDefault(Badge);
