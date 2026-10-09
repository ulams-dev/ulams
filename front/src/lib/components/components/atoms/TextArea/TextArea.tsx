import * as React from "react";
import { RefObject, useCallback, useMemo } from "react";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { cx } from "../../../utils/cx";
import styles from "./TextArea.module.css";

const notTextAreaProps = {
  theme: undefined,
  label: undefined,
  helper: undefined,
  error: undefined,
};

export interface TextAreaProps
  extends React.TextareaHTMLAttributes<HTMLTextAreaElement>,
    ExtendableStyledComponent {
  label?: string | React.ReactNode;
  helper?: React.ReactNode;
  error?: string | React.ReactNode;
  textAreaRef?: RefObject<HTMLTextAreaElement>;
}

export const TextArea: React.FC<TextAreaProps> = (props) => {
  const {
    textAreaRef,
    label,
    required,
    disabled,
    error,
    helper,
    className = "",
  } = props;
  const generateRandomTextAreatId = useMemo(() => {
    const randomString = (Math.random() + 1).toString(36).substring(3);
    return `lms-textarea-id-${randomString}`;
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
        <label htmlFor={generateRandomTextAreatId}>
          {label}
          {required && <span className="required">*</span>}
        </label>
      );
    }
    return <></>;
  }, [generateRandomTextAreatId, label, required]);

  return (
    <div
      className={cx(
        styles.textArea,
        Boolean(error) && styles.error,
        disabled && styles.disabled,
        `ulams-component lsm-input ${helper ? "has-helper" : ""} ${
          error ? "has-error" : ""
        } ${className}`
      )}
    >
      <div className={`textarea-container ${addFilledClass()}`}>
        {renderLabel()}
        <textarea
          {...props}
          {...notTextAreaProps}
          id={label ? generateRandomTextAreatId : undefined}
          ref={textAreaRef}
        >
          {props.value}
        </textarea>
        {helper && <span>{helper}</span>}
        {error && <div className="error">{error}</div>}
      </div>
    </div>
  );
};
