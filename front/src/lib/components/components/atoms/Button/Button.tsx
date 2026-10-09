import * as React from "react";
import { PropsWithChildren } from "react";

import Spin from "../Spin/Spin";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./Button.module.css";
import { legacyDefault } from "../../../utils/legacy";
import { useThemeTokens } from "../../../theme/applyTheme";

const ModeTypes = {
  PRIMARY: "primary",
  SECONDARY: "secondary",
  OUTLINE: "outline",
  WHITE: "white",
  ICON: "icon",
  SECONDARY_OUTLINE: "secondary outline",
  GRAY: "gray",
} as const;

type ModeProp = typeof ModeTypes[keyof typeof ModeTypes];

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    ExtendableStyledComponent {
  children?: React.ReactNode;
  invert?: boolean;
  loading?: boolean;
  block?: boolean;
  mode?: ModeProp;
  as?: React.ElementType;
  "aria-label"?: string;
}

// Main button with styles
export const Button: React.FC<PropsWithChildren<ButtonProps>> = ({
  as,
  children,
  mode = ModeTypes.PRIMARY,
  invert,
  loading = false,
  block = false,
  className = "",
  style,
  ...props
}) => {
  const isOutline = Boolean(mode?.includes(ModeTypes.OUTLINE));
  const tokens = useThemeTokens();
  const disabledBorder =
    isOutline && !invert ? tokens?.primaryButtonDisabled : undefined;

  const loadingColor =
    mode === ModeTypes.OUTLINE
      ? invert
        ? "var(--ulams-opt-color-outline-button-invert, var(--ulams-color-primary))"
        : "var(--ulams-opt-color-outline-button, var(--ulams-color-primary))"
      : "var(--ulams-color-white)";

  const Component: React.ElementType = as ?? "button";

  return (
    <Component
      {...props}
      className={cx(
        styles.button,
        isOutline && styles.outline,
        (mode === ModeTypes.SECONDARY ||
          mode === ModeTypes.SECONDARY_OUTLINE) &&
          styles.secondary,
        mode === ModeTypes.WHITE && styles.white,
        mode === ModeTypes.ICON && styles.icon,
        mode === ModeTypes.GRAY && styles.gray,
        invert && styles.invert,
        disabledBorder && styles.disabledBorder,
        invert && mode !== ModeTypes.OUTLINE && styles.focusGray,
        block && styles.block,
        loading && styles.loading,
        "ulams-component",
        className
      )}
      style={
        disabledBorder
          ? ({ "--btn-disabled-border": disabledBorder, ...style } as React.CSSProperties)
          : style
      }
      role="button"
      aria-labelledby="labeldiv"
    >
      {loading && <Spin color={loadingColor} />}
      {children}
    </Component>
  );
};

export default legacyDefault(Button);
