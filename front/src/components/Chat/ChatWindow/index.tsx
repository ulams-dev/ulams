import ChatMessage from "@/components/Chat/ChatMessage";
import styles from "./ChatWindow.module.css";
import ContentLoader from "@/components/_App/ContentLoader";
import useChatLogic from "@/hooks/chat/useChatLogic";
import { BackArrow } from "@/icons/index";
import { Text } from "@ulams/components";
import { UlamsContext } from "@ulams/sdk/react";
import { useCallback, useContext, useId } from "react";
import { isMobile } from "react-device-detect";
import { useTranslation } from "react-i18next";

type Props = {
  isOpen: boolean;
  lessonID: number;
  onClose: () => void;
};

const ChatWindow: React.FC<Props> = ({ isOpen, lessonID, onClose }) => {
  const { token } = useContext(UlamsContext);
  const { chatState, handleSendMessage, handleInputChange, handleKeyDown } =
    useChatLogic(lessonID, token);

  const { t } = useTranslation();
  const id = useId();

  const handleClose = useCallback(() => {
    const timeoutId = setTimeout(() => {
      onClose();
    }, 200);

    return () => clearTimeout(timeoutId);
  }, [onClose]);

  return (
    <div
      className={`chatwindow ${styles.chatWindow}${
        isMobile ? ` ${styles.mobile}` : ""
      }`}
      data-closing={!isOpen}
    >
      <header className={`chatwindow__header ${styles.header}`}>
        <Text size="18" bold>
          {t("LetsTalk")}
        </Text>
        <button
          title="close-chat"
          className={`chatwindow__header--close ${styles.close}`}
          onClick={handleClose}
        >
          <span></span>
        </button>
      </header>
      <div className={`chatwindow__content ${styles.content}`}>
        <div className={`chatwindow__content--messages ${styles.messages}`}>
          {chatState.messages.length === 0 && (
            <div className={`chatwindow__content--messages__empty ${styles.messagesEmpty}`}>
              <Text size="16">{t("StartChat")}</Text>
            </div>
          )}
          {chatState.messages.map((message, index) => (
            <ChatMessage
              key={`message=${index}-${id}`}
              message={message.message}
              isAI={message.isAi}
            />
          ))}
        </div>
        <div>
          <div className={`chatwindow__content--input ${styles.input}`}>
            <input
              value={chatState.inputValue}
              onChange={handleInputChange}
              onKeyDown={handleKeyDown}
              type="text"
              title="chatmessage"
            />
            <button title="send-message" onClick={handleSendMessage}>
              {chatState.isLoading ? (
                <ContentLoader width="20px" height="20px" />
              ) : (
                <BackArrow />
              )}
            </button>
          </div>
          <div className={`chatwindow__content--error ${styles.error}`}>
            <Text size="11">{chatState.error}</Text>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ChatWindow;
