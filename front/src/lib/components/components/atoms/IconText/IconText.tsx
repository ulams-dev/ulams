import * as React from "react";
import { ReactNode } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import cssStyles from "./IconText.module.css";
import { legacyDefault } from "../../../utils/legacy";

interface Styles {
  icon?: React.CSSProperties;
  text?: React.CSSProperties;
}

export interface IconTextProps
  extends React.HTMLAttributes<HTMLParagraphElement>,
    ExtendableStyledComponent {
  icon?: ReactNode;
  text: string | JSX.Element;
  styles?: Styles;
  noMargin?: boolean;
}

export const IconText: React.FC<IconTextProps> = (props) => {
  const { text, icon, styles, className = "", noMargin, ...pProps } = props;

  return (
    <p
      {...pProps}
      className={cx(
        cssStyles.iconText,
        noMargin && cssStyles.noMargin,
        "ulams-component",
        className
      )}
    >
      {icon && (
        <span
          className="icon"
          style={styles?.icon}
          role="button"
          aria-label={typeof text === "string" ? text : text?.props?.children}
        >
          {icon}
        </span>
      )}

      <div className="text" style={styles?.text}>
        {text}
      </div>
    </p>
  );
};

export default legacyDefault(IconText);
