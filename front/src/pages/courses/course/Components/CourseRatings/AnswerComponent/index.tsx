import { Text } from "@ulams/components/components/atoms/Typography/Text";

import { API } from "@ulams/sdk";
import { FC } from "react";
import styles from "./styles.module.css";

import { Avatar } from "@ulams/components/components/atoms/Avatar/Avatar";
import { Row } from "@ulams/components/components/atoms/Row/index";
import { Stack } from "@ulams/components/components/atoms/Stack/index";
import { APP_CONFIG } from "@/config/app";
import { formatDate } from "@/utils/date";


interface AnswerComponentProps {
  question: API.QuestionAnswer;
}

const AvatarWithInitial: FC<{ name: string }> = ({ name }) => {
  const randomColor = () => {
    return "#" + Math.floor(Math.random() * 16777215).toString(16);
  };

  const initials = name.charAt(0).toUpperCase();

  return (
    <div
      className={styles.randomAvatar}
      style={{ backgroundColor: randomColor() }}
    >
      <Text size={"18"}>{initials}</Text>
    </div>
  );
};

export const AnswerComponent: FC<AnswerComponentProps> = ({ question }) => {
  const { user, note, updated_at } = question;

  if (!note) {
    return null;
  }

  return (
    <div className={styles.answerWrapper}>
      <Row className={styles.container}>
        <Row $gap={19}>
          {user.avatar ? (
            <Avatar src={user.avatar} alt={`user-avatar-${user.name}`} />
          ) : (
            <AvatarWithInitial name={user.name} />
          )}

          <Stack $justifyContent="flex-start" $alignItems="flex-start">
            <Text noMargin className="date" size="13">
              {formatDate(updated_at, APP_CONFIG.defaultDateFormat)}
            </Text>

            <Text className="note" size="13">
              {note}
            </Text>
          </Stack>
        </Row>
      </Row>
    </div>
  );
};
