import ChatWindow from "@/components/Chat/ChatWindow";
import { ChatIcon } from "@/icons/index";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { isMobile } from "react-device-detect";
import type { CSSProperties } from "react";
import styles from "./Chat.module.css";

type Props = {
  lessonID: number;
  placement?: CSSProperties;
};

const AIChat: React.FC<Props> = ({
  lessonID,
  placement = isMobile
    ? {
        right: "0px",
        position: "absolute",
        width: "100%",
        bottom: "-100%",
      }
    : {
        bottom: "-100%",
        right: "15px",
        position: "absolute",
      },
}) => {
  const [state, setState] = useState(false);
  const { t } = useTranslation();
  // `bottom` is driven by the open/close animation; the rest positions the window.
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const { bottom: _bottom, ...placementStyle } = placement;

  return (
    <div
      className={[
        styles.wrapper,
        isMobile && styles.mobile,
        state && styles.open,
      ]
        .filter(Boolean)
        .join(" ")}
    >
      <div
        className={`${styles.container}${state ? ` ${styles.open}` : ""}`}
        style={placementStyle}
      >
        <ChatWindow
          isOpen={state}
          lessonID={lessonID}
          onClose={() => setState(false)}
        />
      </div>
      {!state && (
        <button
          type="button"
          className={`${styles.button}${isMobile ? ` ${styles.mobile}` : ""}`}
          aria-label={t("StartChat")}
          onClick={() => setState(true)}
        >
          <ChatIcon />
        </button>
      )}
    </div>
  );
};

export default AIChat;
