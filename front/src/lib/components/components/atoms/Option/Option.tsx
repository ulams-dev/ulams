import * as React from "react";

import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./Option.module.css";
import { legacyDefault } from "../../../utils/legacy";

export interface OptionType
  extends Omit<React.InputHTMLAttributes<HTMLInputElement>, "type">,
    ExtendableStyledComponent {
  label?: React.ReactNode;
  type: "checkbox" | "radio";
  error?: string | React.ReactNode;
  required?: boolean;
}

export const Option: React.FC<OptionType> = (props) => {
  const { label, type, className = "", required, error } = props;

  if (label) {
    return (
      <div className={styles.container}>
        <div className={styles.wrapper}>
          {required && <span className="required">*</span>}
          <div
            className={cx(
              styles.option,
              type === "radio" && styles.radio,
              "ulams-component",
              `lms-${type}`,
              className
            )}
          >
            <label>
              <input {...props} type={type} /> <span>{label}</span>
            </label>
          </div>
        </div>{" "}
        {error && <div className="error">{error}</div>}
      </div>
    );
  }

  return (
    <div className={styles.container}>
      <div className={styles.wrapper}>
        {required && <span className="required">*</span>}
        <div
          className={cx(
            styles.option,
            type === "radio" && styles.radio,
            "ulams-component"
          )}
        >
          <input {...props} type={type} />{" "}
        </div>{" "}
      </div>
      {error && <div className="error">{error}</div>}
    </div>
  );
};

export default legacyDefault(Option);
