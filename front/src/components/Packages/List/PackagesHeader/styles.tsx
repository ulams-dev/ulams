import { CSSProperties, FC, ReactNode, useContext } from "react";
import { isMobile } from "react-device-detect";
import { PackagesParams } from "@/types/params";
import { PackagesContext } from "../PackagesContext";
import styles from "./styles.module.css";

interface PackagesHeaderStylesProps {
  children: ReactNode | ReactNode[];
}

const titleMarginBottom = (filters: PackagesParams | undefined): string =>
  isMobile
    ? "0"
    : filters && Object.keys(filters).length > 1
    ? "35px"
    : filters && Object.keys(filters).length === 1 && "page" in filters
    ? "-35px"
    : filters === undefined
    ? "-35px"
    : "35px";

const PackagesHeaderStyles: FC<PackagesHeaderStylesProps> = ({ children }) => {
  const { params } = useContext(PackagesContext);

  return (
    <div
      className={styles.root}
      data-mobile={isMobile}
      style={{ "--header-title-mb": titleMarginBottom(params) } as CSSProperties}
    >
      {children}
    </div>
  );
};

export default PackagesHeaderStyles;
