import * as React from "react";
import { ReactNode } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./NoteAction.module.css";

interface StyledNoteProps extends ExtendableStyledComponent {
  color?: string;
}

export interface NoteProps extends StyledNoteProps {
  title: ReactNode;
  subtitle?: ReactNode;
  actions: ReactNode;
}

export const NoteAction: React.FC<NoteProps> = (props) => {
  const { title, subtitle, color, actions, className = "" } = props;
  return (
    <div
      className={cx(styles.note, "ulams-component", className)}
      style={{ "--note-color": color || undefined } as React.CSSProperties}
    >
      <div>
        <div>{title}</div>
        {subtitle && <div className={"subtitle"}>{subtitle}</div>}
      </div>
      <div>{actions}</div>
    </div>
  );
};
