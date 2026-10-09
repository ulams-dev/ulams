import { Text } from "@ulams/components";
import styles from "./ChatMessage.module.css";

type Props = {
  message: string;
  isAI?: boolean;
};

const ChatMessage: React.FC<Props> = ({ message, isAI = false }) => {
  return (
    <div className={`${styles.message}${isAI ? ` ${styles.ai}` : ""}`}>
      <Text size="16">{message}</Text>
    </div>
  );
};

export default ChatMessage;
