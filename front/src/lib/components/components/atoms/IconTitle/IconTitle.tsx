import * as React from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import cssStyles from "./IconTitle.module.css";
import { HeaderLevelInt, HeaderLevelStr } from "../../../types/titleTypes";
import { setFontSizeByHeaderLevel } from "../../../utils/components/primitives/titleUtils";
import { legacyDefault } from "../../../utils/legacy";

interface Styles {
  icon?: React.CSSProperties;
  title?: React.CSSProperties;
  subtitle?: React.CSSProperties;
  container?: React.CSSProperties;
}

interface StyledHeader {
  level?: HeaderLevelInt;
  mobile?: boolean;
  as: keyof JSX.IntrinsicElements;
}

export interface IconTitleProps
  extends StyledHeader,
    React.HTMLAttributes<HTMLHeadingElement>,
    ExtendableStyledComponent {
  title: string;
  subtitle?: string;
  icon: React.ReactNode;
  styles?: Styles;
}

export const IconTitle: React.FC<IconTitleProps> = (props) => {
  const {
    title,
    subtitle,
    icon,
    level = 3,
    styles,
    as,
    className = "",
  } = props;
  const Tag = ((as as HeaderLevelStr) ??
    `h${level}`) as React.ElementType;
  return (
    <Tag
      className={cx(
        cssStyles.iconTitle,
        "lms-icon-title",
        "ulams-component",
        className
      )}
      style={
        {
          // the former styled header never received `mobile`
          "--icon-title-size": setFontSizeByHeaderLevel(level),
          ...styles?.container,
        } as React.CSSProperties
      }
    >
      <span
        className="icon"
        style={styles?.icon}
        role="button"
        aria-label={title}
      >
        {icon}
      </span>
      <span className="full-title">
        <span className="title" style={styles?.title}>
          {title}
        </span>
        {subtitle && (
          <span className="subtitle" style={styles?.subtitle}>
            {subtitle}
          </span>
        )}
      </span>
    </Tag>
  );
};

export default legacyDefault(IconTitle);
