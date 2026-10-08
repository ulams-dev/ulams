import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { IconTime } from "../../../icons";
import styles from "./styles.module.css";

interface TimeInfoProps {
  time: string;
}

const TimeInfo = ({ time }: TimeInfoProps) => {
  return (
    <div className={styles.root}>
      <div className={styles.iconContainer}>
        <IconTime color="#ffffff" width="22px" height="22px" />
      </div>
      <Text className={styles.time}>{time}</Text>
    </div>
  );
};

export default TimeInfo;
