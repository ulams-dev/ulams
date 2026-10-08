import * as React from "react";
import { PropsWithChildren } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./Link.module.css";
import { legacyDefault } from "../../../utils/legacy";

export interface LinkProps
  extends React.AnchorHTMLAttributes<HTMLAnchorElement>,
    ExtendableStyledComponent {
  underline?: boolean;
}

// `underline` is accepted for API compatibility; its animation was disabled in the former styles.
export const Link: React.FC<PropsWithChildren<LinkProps>> = ({
  underline: _underline = false,
  ...props
}) => {
  return (
    <a
      {...props}
      className={cx(styles.link, "ulams-component", props.className ?? "")}
    >
      {props.children}
    </a>
  );
};

export default legacyDefault(Link);
