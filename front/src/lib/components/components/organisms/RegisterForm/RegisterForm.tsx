import { Formik, FormikErrors } from "formik";
import { useContext, useState, useEffect, useCallback } from "react";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import type {
  DefaultResponse,
  DefaultResponseError,
  RegisterResponse,
} from "@ulams/sdk/types";
import type { ResponseError } from "umi-request";

//import "@ulams/ts-models";
//import "@ulams/sdk/types";

import { Input, Button, Title, Link, Text, Checkbox } from "../../../";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { API } from "@ulams/sdk";
import useAdditionalFieldTranslations from "../../../hooks/useAdditionalFieldsTranslations";
import MarkdownRenderer from "../../molecules/MarkdownRenderer/MarkdownRenderer";

import styles from "./RegisterForm.module.css";

type FormValues = {
  first_name: string;
  last_name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone: string;
  error?: string;
};

export interface RegisterFormProps extends ExtendableStyledComponent {
  onError?: (err: ResponseError<DefaultResponseError>) => void;
  onSuccess?: (
    res: DefaultResponse<RegisterResponse>,
    values: FormValues & Record<string, string | boolean>
  ) => void;
  onLoginLink?: () => void;
  mobile?: boolean;
  return_url?: string;
  /** Additional labels you can overwrite fields labels. Usable for additional fields.  */
  fieldLabels?: Record<string, React.ReactNode>;
  submitText?: string;
}

export const RegisterForm: React.FC<RegisterFormProps> = ({
  onSuccess,
  onError,
  onLoginLink,
  mobile = false,
  return_url = "",
  fieldLabels = {},
  className = "",
  submitText,
}) => {
  const [initialValues, setInitialValues] = useState<
    FormValues & Record<string, string | boolean>
  >({
    first_name: "",
    last_name: "",
    email: "",
    password: "",
    password_confirmation: "",
    phone: "",
  });
  const { t } = useTranslation();
  const { register, fields, fetchFields, settings } =
    useContext(UlamsContext);
  const { getFieldTranslations, filterByKey } =
    useAdditionalFieldTranslations();
  useEffect(() => {
    fetchFields({ class_type: "App\\Models\\User" });
  }, []);

  useEffect(() => {
    const additionalFields = (fields && fields.list) || [];

    setInitialValues((prevState) => ({
      ...prevState,
      ...additionalFields.reduce(
        (obj: object, item: API.Metadata) => ({
          ...obj,
          [item.name]: item.type === "boolean" ? false : "",
        }),
        {}
      ),
    }));
  }, [fields]);

  const isAdditionalRequiredField = useCallback(
    (field: API.Metadata) => {
      if (field.extra && Array.isArray(field.extra)) {
        if (
          field.extra?.some(
            (item: Record<string, string | number | boolean>) =>
              item.register === false
          )
        ) {
          return false;
        }
      }

      return field?.rules && field?.rules.length > 0;
    },
    [fields]
  );

  return (
    <div
      className={`ulams-component ${styles.root} ${mobile ? styles.mobile : ""} ${className}`}
    >
      <Title level={3} style={{ maxWidth: "480px", textAlign: "center" }}>
        {submitText ? submitText : t("RegisterForm.Header")}
      </Title>
      <Text level={3}>{t("RegisterForm.Subtitle")}</Text>
      <Formik
        enableReinitialize
        initialValues={initialValues}
        validate={(values) => {
          const errors: FormikErrors<FormValues & Record<string, string>> = {};

          if (!values.first_name) {
            errors.first_name = t("Required");
          }
          if (!values.last_name) {
            errors.last_name = t("Required");
          }
          if (!values.email) {
            errors.email = t("Required");
          } else if (
            !/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i.test(values.email)
          ) {
            errors.email = t("Wrong email");
          }
          if (!values.password) {
            errors.password = t("Required");
          } /*else if (
            !/(?=.*[!@#$%^&*])[a-zA-Z0-9!@#$%^&*]{8,}$/i.test(values.password)
          ) {
            errors.password = t("Bad password");
          }*/
          if (!values.password_confirmation) {
            errors.password_confirmation = t("Required");
          } else if (values.password !== values.password_confirmation) {
            errors.password_confirmation = t("Different passwords");
          }

          if (values.phone && !/\d{9}$/i.test(values.phone)) {
            errors.phone = t("Wrong phone number");
          }

          fields.list &&
            fields.list.map((field: API.Metadata) => {
              if (isAdditionalRequiredField(field)) {
                if (!values[field.name]) {
                  errors[field.name] = t("Required");
                }
              }
            });

          return errors;
        }}
        onSubmit={(values, { setSubmitting, resetForm, setErrors }) => {
          register({
            ...values,
            return_url: `${return_url}`,
          })
            .then((res: DefaultResponse<RegisterResponse>) => {
              resetForm();
              onSuccess?.(res, values);
            })
            .catch((err: ResponseError<DefaultResponseError>) => {
              setErrors({ error: err?.data?.message, ...err.data.errors });
              onError && onError(err);
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
          isSubmitting,
        }) => (
          <form onSubmit={handleSubmit}>
            {errors && errors.error && (
              <Text type="danger">{errors.error}</Text>
            )}
            <Input
              label={fieldLabels["first_name"] || t("First name")}
              type="text"
              name="first_name"
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.first_name}
              error={touched.first_name && errors.first_name}
              required
            />

            <Input
              label={fieldLabels["last_name"] || t("Last name")}
              type="text"
              name="last_name"
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.last_name}
              error={touched.last_name && errors.last_name}
              required
            />

            <Input
              label={fieldLabels["email"] || t("Email")}
              className="form-control grey"
              type="email"
              name="email"
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.email}
              error={touched.email && errors.email}
              required
            />

            <Input
              label={fieldLabels["password"] || t("Password")}
              type="password"
              name="password"
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.password}
              error={touched.password && errors.password}
              helper={t("Password validation")}
              required
            />

            <Input
              label={t("Repeat password")}
              type="password"
              name="password_confirmation"
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.password_confirmation}
              error={
                touched.password_confirmation && errors.password_confirmation
              }
              required
            />

            <Input
              label={fieldLabels["phone"] || t("Phone")}
              type="text"
              name="phone"
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.phone}
              error={touched.phone && errors.phone}
            />

            {(fields.list || [])
              .filter((field: API.Metadata) => {
                const r = filterByKey(field);

                return field.type !== "boolean" && r;
              })
              .map((field: API.Metadata, index: number) => (
                <Input
                  key={`${field}${index}`}
                  required={isAdditionalRequiredField(field)}
                  label={
                    getFieldTranslations(field) ||
                    fieldLabels[`AdditionalFields.${field.name}`] ||
                    t(`AdditionalFields.${field.name}`)
                  }
                  type="text"
                  name={field.name}
                  onChange={handleChange}
                  onBlur={handleBlur}
                  value={String(values[field.name]) || ""}
                  error={errors[field.name] && touched[field.name]}
                />
              ))}

            {(fields.list || [])
              .filter((field) => {
                const r = filterByKey(field);

                return field.type === "boolean" && r;
              })
              .map((field: API.Metadata, index: number) => (
                <Checkbox
                  key={`${field.id}${index}`}
                  label={
                    <div
                      dangerouslySetInnerHTML={{
                        __html:
                          getFieldTranslations(field) ||
                          fieldLabels[`AdditionalFields.${field.name}`] ||
                          t(`AdditionalFields.${field.name}`),
                      }}
                    />
                  }
                  required={isAdditionalRequiredField(field) as boolean}
                  id={field.name + Date.now()}
                  name={field.name}
                  onChange={handleChange}
                  onBlur={handleBlur}
                  error={errors[field.name]}
                />
              ))}

            {settings.value.register?.processing && (
              <div className={styles.processingWrapper}>
                <MarkdownRenderer>
                  {settings.value.register?.processing}
                </MarkdownRenderer>
                {(fields.list || [])
                  .filter((field) => field.name.includes("processing"))
                  .map((field: API.Metadata, index: number) => (
                    <Checkbox
                      key={`${field.id}${index}`}
                      label={
                        getFieldTranslations(field) ||
                        fieldLabels[`AdditionalFields.${field.name}`] ||
                        t(`AdditionalFields.${field.name}`)
                      }
                      required={isAdditionalRequiredField(field) as boolean}
                      id={field.name + Date.now()}
                      name={field.name}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      error={errors[field.name]}
                    />
                  ))}
              </div>
            )}
            {settings.value.register?.clause1 && (
              <div className={styles.clause}>
                <MarkdownRenderer>
                  {settings.value.register.clause1}
                </MarkdownRenderer>
              </div>
            )}

            {settings.value.register?.clause2 && (
              <div className={styles.clause}>
                <MarkdownRenderer>
                  {settings.value.register?.clause2}
                </MarkdownRenderer>
              </div>
            )}

            {/* {settings.value.regiser} */}
            <Button mode="primary" type="submit" loading={isSubmitting} block>
              {submitText ? submitText : t("Login.Signup")}
            </Button>
          </form>
        )}
      </Formik>
      <div className={styles.gotAccount}>
        <Text size="14">
          {t("RegisterForm.Already have an account")}{" "}
        </Text>{" "}
        <Link onClick={() => onLoginLink && onLoginLink()}>
          {t("Login.Signin")}
        </Link>
      </div>
    </div>
  );
};

export default RegisterForm;
