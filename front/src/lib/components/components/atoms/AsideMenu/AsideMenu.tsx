import * as React from "react";
import { PropsWithChildren } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./AsideMenu.module.css";
import { legacyDefault } from "../../../utils/legacy";

interface AsideMenuProps extends ExtendableStyledComponent {
  active?: boolean;
}

export const AsideMenu: React.FC<PropsWithChildren<AsideMenuProps>> = (
  props
) => {
  const { children, active, className = "" } = props;
  return (
    <div
      className={cx(
        styles.asideMenu,
        active && styles.active,
        "ulams-component",
        className
      )}
    >
      {children}
    </div>
  );
};

export default legacyDefault(AsideMenu);
