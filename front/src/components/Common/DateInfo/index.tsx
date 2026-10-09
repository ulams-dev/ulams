import { useMemo } from "react";
import { formatDate } from "@/utils/date";
import { APP_CONFIG } from "@/config/app";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { IconCalendar } from "../../../icons";
import styles from "./styles.module.css";

export enum DateInfoTypes {
  "ACCEPTED",
  "WAITING",
  "ENDED",
  "DEFAULT",
}

interface DateInfoProps {
  type: DateInfoTypes;
  date?: Date | string | number;
  info?: string | React.ReactElement;
}

const DateInfo = ({ type, date, info }: DateInfoProps) => {
  const color = useMemo(() => {
    switch (type) {
      case DateInfoTypes.ACCEPTED:
        return "#198754";
      case DateInfoTypes.WAITING:
        return "#FFC300";
      case DateInfoTypes.ENDED:
        return "#D22B2B";
      default:
        return "var(--ulams-color-primary)";
    }
  }, [type]);

  return (
    <div className={styles.root}>
      <div
        className={styles.dateContainer}
        style={{
          borderColor: color,
        }}
      >
        <div
          className={styles.iconContainer}
          style={{
            backgroundColor: color,
          }}
        >
          <IconCalendar color="#ffffff" />
        </div>
        <Text className={styles.date}>
          {date
            ? formatDate(new Date(date), APP_CONFIG.defaultDateTimeFormat)
            : "--"}
        </Text>
      </div>
      {info && (
        <div
          className={styles.info}
          style={{
            borderColor: color,
          }}
        >
          {info}
        </div>
      )}
    </div>
  );
};

export default DateInfo;
