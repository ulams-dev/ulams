import * as React from "react";
import { ReactNode } from "react";
import chroma from "chroma-js";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { useThemeTokens } from "../../../theme/applyTheme";
import { cx } from "../../../utils/cx";
import styles from "./PricingCard.module.css";
import { legacyDefault } from "../../../utils/legacy";

interface StyledPricingCardProps extends ExtendableStyledComponent {
  mobile?: boolean;
  free?: boolean;
}

export interface PricingCardProps extends StyledPricingCardProps {
  children: ReactNode;
}

export const PricingCard: React.FC<PricingCardProps> = (props) => {
  const { children, mobile, free, className = "" } = props;
  const tokens = useThemeTokens();

  // Dark-mode footer border: the dark background brightened by 1 (chroma), not expressible in CSS.
  const darkFooterBorder = React.useMemo(() => {
    try {
      return tokens?.dm__background
        ? chroma(tokens.dm__background).brighten(1).hex()
        : undefined;
    } catch {
      return undefined;
    }
  }, [tokens?.dm__background]);

  return (
    <div
      className={cx(
        styles.pricingCard,
        mobile && styles.mobile,
        free && styles.free,
        "ulams-component",
        className
      )}
      style={
        darkFooterBorder
          ? ({ "--pc-dark-footer-border": darkFooterBorder } as React.CSSProperties)
          : undefined
      }
    >
      {children}
    </div>
  );
};

export default legacyDefault(PricingCard);
