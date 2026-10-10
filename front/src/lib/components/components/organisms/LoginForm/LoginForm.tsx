import { Formik, FormikProps } from "formik";
import { useContext, useEffect, useRef } from "react";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import type {
  DefaultResponseError,
  DefaultResponse,
} from "@ulams/sdk/types";
import type { ResponseError } from "umi-request";

import { Input, Button, Title, Link, Text, Checkbox } from "../../../";
import { ExtendableStyledComponent } from "@ulams/components/types/component";

import styles from "./LoginForm.module.css";

interface MyFormValues {
  email: string;
  password: string;
  remember_me: boolean;
  error?: string;
}

interface Props extends ExtendableStyledComponent {
  onError?: (err: DefaultResponse<DefaultResponseError>) => void;
  onSuccess?: () => void;
  onResetPasswordLink?: () => void;
  onRegisterLink?: () => void;
  mobile?: boolean;
  submitText?: string;
}

export const LoginForm: React.FC<Props> = ({
  onSuccess,
  onError,
  onResetPasswordLink,
  onRegisterLink,
  mobile = false,
  className = "",
  submitText,
}) => {
  const initialValues: MyFormValues = {
    email: "",
    password: "",
    remember_me: false,
  };
  const { t } = useTranslation();
  const { login, user } = useContext(UlamsContext);

  const formikRef = useRef<FormikProps<MyFormValues>>(null);

  useEffect(() => {
    if (user.error) {
      formikRef.current?.setErrors({
        // WTF. Error from the API is not consisted with rest of the responses
        // eslint-disable-next-line @typescript-eslint/ban-ts-comment
        // @ts-ignore
        error: user?.error?.data?.message || user?.error?.message,
        ...user?.error?.errors,
      });
      onError?.(user.error);
    } else {
      formikRef.current?.setErrors({});
    }
  }, [user.error]);

  useEffect(() => {
    if (user.value) {
      onSuccess?.();
    }
  }, [user.value, onSuccess]);

  return (
    <div
      className={`ulams-component ${styles.root} ${mobile ? styles.mobile : ""} ${className}`}
    >
      <Title level={3}>{t("Login.Header")}</Title>{" "}
      <Formik
        innerRef={formikRef}
        initialValues={initialValues}
        validate={(values) => {
          const errors: Partial<MyFormValues> = {};

          if (!values.email) {
            errors.email = t("Required");
          }

          if (!values.password) {
            errors.password = t("Required");
          }

          return errors;
        }}
        onSubmit={(values, { setSubmitting, setErrors }) => {
          login({
            ...values,
            remember_me: values.remember_me ? 1 : 0,
          })
            .catch((err: ResponseError<DefaultResponseError>) => {
              setErrors({
                error: err?.data?.message,
                ...(err?.data?.errors || {}),
              });
              onError?.(err?.data);
            })
            .finally(() => setSubmitting(false));
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

          /* and other goodies */
        }) => (
          <form onSubmit={handleSubmit}>
            {errors && errors.error && (
              <Text type="danger">{errors.error}</Text>
            )}
            <Input
              type="email"
              name="email"
              label="Email"
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.email}
              error={touched.email && errors.email}
            />
            <Input
              type="password"
              name="password"
              label={t("Password")}
              onChange={handleChange}
              onBlur={handleBlur}
              value={values.password}
              error={touched.password && errors.password}
            />
            <Checkbox
              name="remember_me"
              label={t("Login.RememberMe")}
              value={String(values.remember_me)}
              checked={values.remember_me}
              onChange={handleChange}
            />
            <Button
              mode="primary"
              type="submit"
              loading={isSubmitting || user.loading}
              block
            >
              {t("Login.Signin")}
            </Button>
          </form>
        )}
      </Formik>
      <Text size="14">
        <Link
          underline
          onClick={() => onResetPasswordLink && onResetPasswordLink()}
        >
          {t("Login.NotRemember")}
        </Link>
      </Text>
      {!submitText && (
        <>
          <Text size="14">{t("Login.NoAccount")} </Text>
          <Button
            mode={"outline"}
            onClick={() => onRegisterLink && onRegisterLink()}
          >
            {submitText ? submitText : t("Login.Signup")}
          </Button>
        </>
      )}
    </div>
  );
};

export default LoginForm;
