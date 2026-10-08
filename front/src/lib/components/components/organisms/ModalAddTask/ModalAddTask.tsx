import { Button, Input, Title } from "../../..";
import { API } from "@ulams/sdk";
import { DefaultResponseError } from "@ulams/sdk/types";
import {
  RelatedTreeSelect,
  RelatedValue,
} from "../../molecules/RelatedTreeSelect";
import { Formik, FormikErrors, FormikHelpers } from "formik";
import { FC } from "react";
import { useTranslation } from "react-i18next";
import { ResponseError } from "umi-request";

import styles from "./ModalAddTask.module.css";

interface AddTaskFormValues {
  title: string;
  description?: string;
  related?: RelatedValue;
  error?: string;
  related_type?: string;
  related_id?: string;
}

interface Props {
  onSuccess?: () => void;
  onCancel?: () => void;
  onError?: (err: ResponseError<DefaultResponseError>) => void;
  // Callers pass the SDK's addTask (CreateTaskRequest, numeric related_id); the
  // former CSS-in-JS wrapper erased this prop type, so keep it loose.
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  addTask: (data: any) => Promise<API.DefaultResponse<API.Task>>;
  refreshTasks: () => void;
}

const ModalAddTask: FC<Props> = ({
  onSuccess,
  onError,
  onCancel,
  addTask,
  refreshTasks,
}) => {
  const initialValues = {
    title: "",
  } as AddTaskFormValues;

  const { t } = useTranslation();

  const validate = (values: AddTaskFormValues) => {
    const errors: FormikErrors<AddTaskFormValues & Record<string, string>> = {};

    if (!values.title) {
      errors.title = "Required";
    }
    return errors;
  };

  return (
    <div className={styles.wrapper}>
      <header className={styles.header}>
        <Title>{t<string>("Tasks.AddNewTask")}</Title>
      </header>
      <Formik
        initialValues={initialValues}
        validate={validate}
        onSubmit={(
          { related, ...values },
          {
            setSubmitting,
            resetForm,
            setErrors,
          }: FormikHelpers<AddTaskFormValues>
        ) => {
          let sendValues: AddTaskFormValues = {
            ...values,
          };

          if (related && related.includes(":")) {
            const [related_type, related_id] = related.split(":");
            sendValues = { ...sendValues, related_type, related_id };
          }
          addTask(sendValues)
            .then(() => {
              refreshTasks();
              resetForm();
              onSuccess?.();
            })
            .catch((err: ResponseError<DefaultResponseError>) => {
              setErrors({ error: err?.data?.message, ...err.data.errors });
              onError?.(err);
            })
            .finally(() => {
              setSubmitting(false);
            });
        }}
      >
        {({
          values,
          errors,
          touched,
          handleChange,
          handleBlur,
          handleSubmit,
          setFieldValue,
          isSubmitting,
        }) => (
          <form onSubmit={handleSubmit}>
            <Input
              error={
                touched.title && errors.title ? (
                  <p>{errors.title}</p>
                ) : undefined
              }
              label={t<string>("Tasks.Title")}
              placeholder={t<string>("Tasks.Title")}
              type="text"
              name="title"
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.title}
              required
            />
            <Input
              error={
                touched.description && errors.description ? (
                  <p>{errors.description}</p>
                ) : undefined
              }
              label={t<string>("Tasks.Description")}
              placeholder={t<string>("Tasks.Description")}
              type="text"
              name="description"
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.description}
            />
            <RelatedTreeSelect
              id="related-tree-select"
              label={t<string>("Tasks.RelatesTo")}
              notFoundContent={t<string>("Tasks.NoContent")}
              value={values.related}
              onChange={(v) => setFieldValue("related", v)}
              onBlur={handleBlur}
            />
            <div className={styles.buttons}>
              <Button type="button" mode="secondary" onClick={onCancel}>
                {t<string>("Tasks.Cancel")}
              </Button>
              <Button type="submit" mode="secondary" disabled={isSubmitting}>
                {t<string>("Tasks.Submit")}
              </Button>
            </div>
          </form>
        )}
      </Formik>
    </div>
  );
};

export default ModalAddTask;
