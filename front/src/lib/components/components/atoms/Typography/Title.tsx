import * as React from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { HeaderLevelInt, HeaderLevelStr } from "../../../types/titleTypes";
import { setFontSizeByHeaderLevel } from "../../../utils/components/primitives/titleUtils";
import { cx } from "../../../utils/cx";
import styles from "./Title.module.css";
import { legacyDefault } from "../../../utils/legacy";

interface StyledHeader {
  level?: HeaderLevelInt;
  mobile?: boolean;
}
export interface TitleProps
  extends StyledHeader,
    React.HTMLAttributes<HTMLHeadingElement>,
    ExtendableStyledComponent {
  children?: React.ReactNode;
  as?: keyof JSX.IntrinsicElements;
}

export const Title: React.FC<TitleProps> = ({
  children,
  level = 1,
  mobile = false,
  as,
  className = "",
  style,
  ...props
}) => {
  const Tag = ((as as HeaderLevelStr) ?? `h${level}`) as React.ElementType;

  return (
    <Tag
      {...props}
      style={
        {
          "--title-size": setFontSizeByHeaderLevel(level, mobile),
          ...style,
        } as React.CSSProperties
      }
      className={cx(styles.title, "ulams-component", className)}
    >
      {children}
    </Tag>
  );
};

export default legacyDefault(Title);
