import * as React from "react";

import { PropsWithChildren } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./CourseProgress.module.css";
import { legacyDefault } from "../../../utils/legacy";

export interface TitleProps extends ExtendableStyledComponent {
  progress: number;
  title: string;
  children: React.ReactNode;
  icon?: React.ReactNode;
  logged?: boolean;
}

export const CourseProgress: React.FC<PropsWithChildren<TitleProps>> = (
  props
) => {
  const { title, children, icon, progress, className = "" } = props;

  return (
    <div
      title={title}
      style={{ "--cp-progress": `${100 * progress}%` } as React.CSSProperties}
      className={cx(
        styles.courseProgress,
        Boolean(icon) && styles.withIcon,
        "ulams-component",
        className
      )}
    >
      <div className="header">
        {icon}
        <span className="title">{title}</span>
      </div>

      <div className="range">
        <div className="knob-wrapper">
          <div className="knob" style={{ left: `${100 * progress}%` }}></div>
        </div>
      </div>

      <div className="description">{children}</div>
    </div>
  );
};

export default legacyDefault(CourseProgress);
