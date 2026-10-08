import * as React from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./Logo.module.css";
import { legacyDefault } from "../../../utils/legacy";

export interface LogoProps
  extends React.ImgHTMLAttributes<HTMLImageElement>,
    ExtendableStyledComponent {
  isSmall?: boolean;
  alt: string;
}

export const Logo: React.FC<LogoProps> = ({ isSmall, className, ...props }) => (
  <img
    {...props}
    className={cx(
      styles.logo,
      isSmall && styles.small,
      "ulams-component",
      className ?? ""
    )}
  />
);

export default legacyDefault(Logo);
