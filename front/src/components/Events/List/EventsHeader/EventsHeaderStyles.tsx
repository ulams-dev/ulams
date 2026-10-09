import { CSSProperties, FC, ReactNode, useContext } from "react";
import { isMobile } from "react-device-detect";
import { API } from "@ulams/sdk";
import { EventsContext } from "@/components/Events/List/EventsContext";
import styles from "./EventsHeader.module.css";

interface EventsHeaderStylesProps {
  children: ReactNode | ReactNode[];
}

const titleMarginBottom = (filters: API.EventsParams | undefined): string =>
  isMobile
    ? "0"
    : filters && Object.keys(filters).length > 1
    ? "35px"
    : filters && Object.keys(filters).length === 1 && "page" in filters
    ? "-35px"
    : filters === undefined
    ? "-35px"
    : "35px";

const EventsHeaderStyles: FC<EventsHeaderStylesProps> = ({ children }) => {
  const { params } = useContext(EventsContext);

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

export default EventsHeaderStyles;
