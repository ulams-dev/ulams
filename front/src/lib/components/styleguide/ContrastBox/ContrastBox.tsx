import * as React from "react";

import { contrast } from "chroma-js";

import { useThemeTokens } from "../../theme/applyTheme";
import orangeTheme from "../../theme/orange";
import { cx } from "../../utils/cx";
import styles from "./ContrastBox.module.css";

export interface TitleProps extends React.HTMLAttributes<HTMLHeadingElement> {
  children?: React.ReactNode;
  lightContrast?: boolean;
}

export const ContrastBox: React.FC<{
  children?: React.ReactNode;
}> = ({ children }) => {
  const tokens = useThemeTokens();
  const primary = tokens?.primaryColor ?? orangeTheme.primaryColor;

  const cts = React.useMemo(() => {
    try {
      return contrast("#fff", primary) >= 5;
    } catch {
      return false;
    }
  }, [primary]);

  return (
    <div className={cx(styles.contrastBox, cts && styles.lightContrast)}>
      {children}
    </div>
  );
};

export default ContrastBox;
