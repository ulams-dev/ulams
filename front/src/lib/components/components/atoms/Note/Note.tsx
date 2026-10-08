import * as React from "react";
import { ReactNode } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { Text } from "../Typography/Text";
import { cx } from "../../../utils/cx";
import styles from "./Note.module.css";

interface StyledNoteProps extends ExtendableStyledComponent {
  color?: string;
}

export interface NoteProps extends StyledNoteProps {
  description: ReactNode;
  time?: ReactNode;
}

export const Note: React.FC<NoteProps> = (props) => {
  const { description, time, color, className = "" } = props;
  return (
    <div
      className={cx(styles.note, "ulams-component", className)}
      style={{ "--note-color": color || undefined } as React.CSSProperties}
    >
      <Text className="description">{description}</Text>
      <Text className="time">{time}</Text>
    </div>
  );
};
