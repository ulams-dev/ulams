import * as React from "react";
import { useMemo, useCallback } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./Input.module.css";
import { legacyDefault } from "../../../utils/legacy";

export interface InputProps
  extends Omit<React.InputHTMLAttributes<HTMLInputElement>, "type">,
    ExtendableStyledComponent {
  label?: string | React.ReactNode;
  helper?: React.ReactNode;
  error?: string | React.ReactNode;
  container?: React.HTMLAttributes<HTMLDivElement>;
  type: "email" | "number" | "password" | "search" | "tel" | "text" | "url";
}

const notInputProps = {
  theme: undefined,
  container: undefined,
  label: undefined,
  helper: undefined,
  error: undefined,
};

export const Input: React.FC<InputProps> = (props) => {
  const { label, helper, container, error, required, className = "" } = props;

  const generateRandomInputId = useMemo(() => {
    const randomString = (Math.random() + 1).toString(36).substring(3);
    return `lms-input-id-${randomString}`;
  }, []);

  const addFilledClass = useCallback(() => {
    const { value, placeholder } = props;
    if ((value && value !== "") || (placeholder && placeholder !== "")) {
      return "filled";
    }
    return "";
  }, [props.value, props.placeholder]);

  const renderLabel = useCallback(() => {
    if (label) {
      return (
        <label htmlFor={generateRandomInputId}>
          {label}
          {required && <span className="required">*</span>}
        </label>
      );
    }
    return <></>;
  }, [generateRandomInputId, label, required]);

  return (
    <div
      {...container}
      className={cx(
        styles.input,
        Boolean(error) && styles.error,
        props.disabled && styles.disabled,
        Boolean(label) && styles.withLabel,
        `ulams-component lsm-input ${helper ? "has-helper" : ""} ${
          error ? "has-error" : ""
        } ${container?.className ? container.className : ""} ${className}`
      )}
    >
      <div className={`input-container ${addFilledClass()}`}>
        {renderLabel()}
        <div className="input-and-fieldset" aria-labelledby="labeldiv">
          <input
            {...props}
            {...notInputProps}
            id={label ? generateRandomInputId : undefined}
          />
          {label ? (
            <fieldset className="fieldset" aria-labelledby="labeldiv">
              <legend>
                {label}
                {required ? "*" : ""}
              </legend>
            </fieldset>
          ) : (
            <span className="fieldset"></span>
          )}
        </div>
      </div>
      {helper && <span className="helper">{helper}</span>}
      {error && <div className="error">{error}</div>}
    </div>
  );
};

export default legacyDefault(Input);
