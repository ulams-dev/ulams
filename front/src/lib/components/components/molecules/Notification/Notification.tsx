import React from "react";
import format from "date-fns/format";
import isToday from "date-fns/isToday";
import { Icon, Text } from "../../../";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import styles from "./Notification.module.css";

export interface ComponentProps extends ExtendableStyledComponent {
  notification: NotificationProps;
  onClick: () => void;
  maxLengthDesc?: number;
  modularView?: boolean;
}

export interface NotificationProps {
  id: string;
  unread: boolean;
  title: string;
  description: string;
  dateTime: Date;
}

export const Notification: React.FC<ComponentProps> = ({
  notification,
  onClick,
  maxLengthDesc,
  modularView = false,
  className = "",
}) => {
  const { unread, title, description, dateTime } = notification;

  return (
    <div
      className={`ulams-component ${styles.root} ${
        modularView ? styles.modularView : ""
      } ${className}`}
    >
      <div className={`header ${styles.header}`}>
        <Text size={"12"} className={`date ${styles.date}`}>
          {format(dateTime, isToday(dateTime) ? "hh:mm" : "dd.MM.yyyy")}
        </Text>
        <button onClick={onClick} title={"notification-read"}>
          <Icon name="close" />
        </button>
      </div>
      <div className={`content ${styles.content}`}>
        <Text size="13" bold={unread}>
          {title}
        </Text>
        <Text size={"14"} noMargin>
          {maxLengthDesc && description.length > maxLengthDesc
            ? `${description.substring(0, maxLengthDesc)}...`
            : description}
        </Text>
      </div>
    </div>
  );
};

export default Notification;
