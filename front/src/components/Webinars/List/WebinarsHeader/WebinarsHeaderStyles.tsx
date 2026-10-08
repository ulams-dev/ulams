import { CSSProperties, FC, ReactNode, useContext } from "react";
import { isMobile } from "react-device-detect";
import { API } from "@ulams/sdk";
import { WebinarsContext } from "@/components/Webinars/List/WebinarsContext";
import styles from "./WebinarsHeader.module.css";

interface WebinarsHeaderStylesProps {
  children: ReactNode | ReactNode[];
}

const titleMarginBottom = (filters: API.WebinarParams | undefined): string =>
  isMobile
    ? "0"
    : filters && Object.keys(filters).length > 1
    ? "35px"
    : filters && Object.keys(filters).length === 1 && "page" in filters
    ? "-35px"
    : filters === undefined
    ? "-35px"
    : "35px";

const WebinarsHeaderStyles: FC<WebinarsHeaderStylesProps> = ({ children }) => {
  const { params } = useContext(WebinarsContext);

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

export default WebinarsHeaderStyles;
