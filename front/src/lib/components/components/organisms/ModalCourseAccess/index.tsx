import React, { useContext } from "react";
import { useTranslation } from "react-i18next";
import { Formik } from "formik";
import { ResponseError } from "umi-request";
import { API } from "@ulams/sdk";
import { UlamsContext } from "@ulams/sdk/react";
import { DefaultResponseError } from "@ulams/sdk/types";

import { Button } from "../../atoms/Button/Button";
import { Input } from "../../atoms/Input/Input";
import { TextArea } from "../../atoms/TextArea/TextArea";
import { Title } from "../../atoms/Typography/Title";
import { Text } from "../../atoms/Typography/Text";

import styles from "./ModalCourseAccess.module.css";

export interface EnquiryFormValues {
  phone_number?: string;
  contact_details?: string;
  error?: string;
}

interface Props {
  course: { id: number; title: string };
  className?: string;
  initialValues?: EnquiryFormValues;
  onSuccess?: () => void;
  onError?: () => void;
  onCancel?: () => void;
}

export const ModalCourseAccess: React.FC<Props> = ({
  course,
  className,
  initialValues = { phone_number: "", contact_details: "" },
  onCancel,
  onSuccess,
  onError,
}) => {
  const { t } = useTranslation();
  const { addCourseAccess } = useContext(UlamsContext);

  return (
    <aside
      className={`ulams-component ${styles.root} ${className}`}
      data-testid="modal-course-access"
    >
      <header>
        <Title level={1}>{course.title}</Title>
        <Text size="14" bold>
          {t("ModalCourseAccess.Title")}
        </Text>
      </header>
      <Formik
        initialValues={initialValues}
        onSubmit={(values, { setErrors, setSubmitting, resetForm }) => {
          const payload: API.CourseAccessEnquiryCreateRequest = {
            course_id: course.id,
            data: values,
          };

          addCourseAccess(payload)
            .then(() => {
              resetForm();
              onSuccess?.();
            })
            .catch((err: ResponseError<DefaultResponseError>) => {
              setErrors({ error: err?.data?.message, ...err?.data?.errors });
              onError?.();
            })
            .finally(() => {
              setSubmitting(false);
            });
        }}
      >
        {({
          values,
          touched,
          errors,
          handleChange,
          handleBlur,
          handleSubmit,
        }) => (
          <form onSubmit={handleSubmit}>
            <div className="form-content">
              {errors && errors.error && (
                <Text className="error-msg" size="12" bold>
                  {errors.error}
                </Text>
              )}
              <div className="input-group">
                <Input
                  type="text"
                  label={t("ModalCourseAccess.PhoneNumber")}
                  error={touched.phone_number && errors.phone_number}
                  id="phone_number"
                  name="phone_number"
                  onChange={handleChange}
                  onBlur={handleBlur}
                  value={values.phone_number}
                />
                <TextArea
                  label={t("ModalCourseAccess.ContactDetails")}
                  error={touched.contact_details && errors.contact_details}
                  id="contact_details"
                  name="contact_details"
                  onChange={handleChange}
                  onBlur={handleBlur}
                  value={values.contact_details}
                />
              </div>
            </div>
            <div className="button-group">
              <Button type="button" mode="secondary" onClick={onCancel}>
                {t("ModalCourseAccess.Cancel")}
              </Button>
              <Button type="submit" mode="secondary">
                {t("ModalCourseAccess.Submit")}
              </Button>
            </div>
          </form>
        )}
      </Formik>
    </aside>
  );
};

export default ModalCourseAccess;
