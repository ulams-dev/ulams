import { Text } from "@ulams/components/components/atoms/Typography/Text";
import styles from "./InfoBox.module.css";

interface InfoBoxProps {
  title: string | React.ReactElement;
  content: string | React.ReactElement;
}

const InfoBox = ({ title, content }: InfoBoxProps) => {
  return (
    <div className={styles.infoBox}>
      {typeof title === "string" ? (
        <Text className={styles.title}>{title}</Text>
      ) : (
        title
      )}
      {typeof content === "string" ? (
        <Text className={styles.content}>{content}</Text>
      ) : (
        content
      )}
    </div>
  );
};

export default InfoBox;
