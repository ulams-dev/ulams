import { FC, PropsWithChildren } from "react";
import { isMobile } from "react-device-detect";
import styles from "./styles.module.css";

export const DetailsSidebarContainer: FC<PropsWithChildren> = ({
  children,
}) => (
  <div className={styles.root} data-mobile={isMobile}>
    {children}
  </div>
);
