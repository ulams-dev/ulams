import React from "react";
import { useTranslation } from "react-i18next";
import { API } from "@ulams/sdk";
import { Title } from "../../../";

import { PersonalContent } from "./content/PersonalContent";
import { IncomingContent } from "./content/IncomingContent";
import styles from "./TaskDetailsModal.module.css";

interface Props {
  task: API.Task & { has_notes: boolean };
  closeModal: () => void;
  onTaskStatusUpdateSuccess?: () => void;
  onTaskUpdateSuccess?: () => void;
  onTaskUpdateError?: () => void;
}

export const TaskDetailsModal: React.FC<Props> = ({
  task,
  closeModal,
  onTaskUpdateSuccess,
  onTaskUpdateError,
  onTaskStatusUpdateSuccess,
}) => {
  const { t } = useTranslation();
  const isPersonal = task.created_by?.id === task.user?.id;
  return (
    <aside className={styles.wrapper}>
      <Title level={4}>{t<string>("Tasks.DetailTask")}</Title>
      {isPersonal ? (
        <PersonalContent
          taskForAction={task}
          onStatusUpdateSuccess={onTaskStatusUpdateSuccess}
          onSuccess={onTaskUpdateSuccess}
          onError={onTaskUpdateError}
          closeModal={closeModal}
        />
      ) : (
        <IncomingContent
          taskForAction={task}
          onTaskStatusUpdateSuccess={onTaskStatusUpdateSuccess}
        />
      )}
    </aside>
  );
};
