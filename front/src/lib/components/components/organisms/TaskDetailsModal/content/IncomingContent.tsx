import React, { ChangeEvent, useContext, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { format } from "date-fns";
import { API } from "@ulams/sdk";
import { UlamsContext } from "@ulams/sdk/react";
import {
  Checkbox,
  Input,
  Row,
  Stack,
  Text,
  TextArea,
  Title,
  Icon,
} from "../../../../";
import { RelatedTreeSelect } from "../../../molecules/RelatedTreeSelect";

import styles from "../TaskDetailsModal.module.css";

interface Props {
  taskForAction: API.Task & { has_notes: boolean };
  onTaskStatusUpdateSuccess?: () => void;
}

export const IncomingContent: React.FC<Props> = ({
  taskForAction,
  onTaskStatusUpdateSuccess,
}) => {
  const { updateTaskStatus, fetchTask, task } = useContext(UlamsContext);
  const { t } = useTranslation();

  useEffect(() => {
    fetchTask(taskForAction.id);
  }, [fetchTask, taskForAction.id]);
  return (
    <Row className={styles.contentRow}>
      <div className={styles.leftCol}>
        <div className={styles.sectionHeader}>
          <Row $gap={16}>
            <Checkbox
              disabled
              checked={!!taskForAction.completed_at}
              onChange={(e: ChangeEvent<HTMLInputElement>) =>
                updateTaskStatus(taskForAction.id, e.target.checked).then(
                  () => {
                    onTaskStatusUpdateSuccess?.();
                  }
                )
              }
            />
            <Title
              className={`${styles.title} ${
                taskForAction.completed_at ? styles.titleCompleted : ""
              }`}
            >
              {taskForAction.title}
            </Title>
          </Row>
          {taskForAction.related_id && taskForAction.related_type && (
            <Text className={styles.programmeText}>
              <RelatedTreeSelect
                disabled
                value={`${taskForAction.related_type}:${taskForAction.related_id}`}
              />
            </Text>
          )}
          {taskForAction.description && (
            <TextArea
              name="description"
              id="description"
              defaultValue={taskForAction.description}
              disabled
            />
          )}
          <div className={styles.notesContainer}>
            <Row $alignItems="center" $gap={4}>
              <Icon name="note" />
              <Title level={5}>{t<string>("Tasks.Notes")}</Title>
            </Row>
            <Stack $gap={8}>
              {task.value?.notes && task.value.notes.length > 0 ? (
                task.value.notes.map((noteItem) => (
                  <div className={styles.note} key={noteItem.id}>
                    <Text>{noteItem.note}</Text>
                  </div>
                ))
              ) : (
                <Text>{t<string>("Tasks.NoNotes")}</Text>
              )}
            </Stack>
          </div>
        </div>
      </div>
      <div className={styles.rightCol}>
        <Text noMargin>{t("TaskDetails.Due", { defaultValue: "Due" })}</Text>
        <div className={styles.dueDate}>
          <Icon name="calendar" />
          <Input
            type="date"
            disabled
            label={t<string>("Tasks.DueDate")}
            placeholder={t<string>("Tasks.DueDate")}
            name="due_date"
            id="due_date"
            value={format(new Date(taskForAction.due_date), "yyyy-MM-dd")}
          />
        </div>
      </div>
    </Row>
  );
};
