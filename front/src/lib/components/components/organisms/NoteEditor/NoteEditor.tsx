import * as React from "react";
import { useState } from "react";
import { Text } from "../../atoms/Typography/Text";
import { Input } from "../../atoms/Input/Input";
import { TextArea } from "../../atoms/TextArea/TextArea";
import Button from "../../atoms/Button/Button";
import Link from "../../atoms/Link/Link";
import type { DefaultResponseError } from "@ulams/sdk/types";
import type { ResponseError } from "umi-request";
import { Formik } from "formik";
import { t } from "i18next";
import { ExtendableStyledComponent } from "@ulams/components/types/component";

import styles from "./NoteEditor.module.css";

const SingleColor: React.FC<SingleColorProps> = ({ color, active }) => (
  <div
    className={`${styles.singleColor} ${active ? styles.active : ""}`}
    style={{ "--note-color": color } as React.CSSProperties}
  />
);

interface NoteEditorProps extends ExtendableStyledComponent {
  onSuccess?: () => void;
  onError?: (err: ResponseError<DefaultResponseError>) => void;
}

interface SingleColorProps {
  color: string;
  active?: boolean;
}

interface FormValues {
  title: string;
  description: string;
  color: string;
}

const initialValues: FormValues = {
  title: "",
  description: "",
  color: "#EB5757",
};

export const NoteEditor: React.FC<NoteEditorProps> = ({
  onSuccess,
  className = "",
}) => {
  const [selectedColor, setSelectedColor] = useState("#EB5757");
  const colors: { color: string }[] = [
    { color: "#EB5757" },
    { color: "#F2994A" },
    { color: "#F2C94C" },
    { color: "#56CCF2" },
  ];
  return (
    <div className={`ulams-component ${styles.popup} ${className}`}>
      <Formik
        initialValues={initialValues}
        validate={(values) => {
          const errors: Partial<FormValues> = {};
          if (!values.title) {
            errors.title = t("Required");
          }
          if (!values.description) {
            errors.description = t("Required");
          }
          return errors;
        }}
        onSubmit={(values) => {
          console.log(values);
          onSuccess && onSuccess();
        }}
      >
        {({
          values,
          errors,
          touched,
          handleChange,
          handleBlur,
          handleSubmit,
          isSubmitting,
          setFieldValue,
        }) => (
          <form onSubmit={handleSubmit}>
            <Text className="form-title">{t<string>("NoteEditor.Title")}</Text>
            <Input
              type="text"
              label={t<string>("NoteEditor.titleInputLabel")}
              id="title"
              name="title"
              placeholder={t<string>("NoteEditor.titleInputPlaceholder")}
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.title}
              error={touched.title && errors.title}
            />
            <TextArea
              id="description"
              name="description"
              placeholder={t<string>("NoteEditor.descInputPlaceholder")}
              label={t<string>("NoteEditor.descInputLabel")}
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.description}
              error={touched.description && errors.description}
              rows={8}
            />
            <div className={styles.colorPicker}>
              <div className="label">{t<string>("NoteEditor.MarkColor")}</div>
              <div className="colors-container">
                {colors.map((color, index) => (
                  <button
                    key={index}
                    type="button"
                    onClick={() => {
                      setSelectedColor(color.color);
                      setFieldValue("color", color.color);
                    }}
                    aria-label={color.color}
                  >
                    <SingleColor
                      color={color.color}
                      active={selectedColor === color.color}
                    />
                  </button>
                ))}
              </div>
            </div>
            <div className="buttons-container">
              <Button type="submit" loading={isSubmitting} mode="secondary">
                {t<string>("NoteEditor.Save")}
              </Button>
              <Link>{t<string>("NoteEditor.Discard")}</Link>
            </div>
          </form>
        )}
      </Formik>
    </div>
  );
};

export default NoteEditor;
