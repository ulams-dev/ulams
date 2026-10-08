import { Formik, FormikErrors } from "formik";
import { useContext, useState, useEffect, useCallback } from "react";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import type { DefaultResponseError } from "@ulams/sdk/types";
import type { ResponseError } from "umi-request";

import { API } from "@ulams/sdk";
import { Upload } from "../../molecules/Upload/Upload";

import { Input, Button, Text, Checkbox, TextArea } from "../../../";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import useAdditionalFieldTranslations from "../../../hooks/useAdditionalFieldsTranslations";

import styles from "./MyProfileForm.module.css";

type FormValues = {
  first_name?: string;
  last_name?: string;
  email?: string | null;
  phone?: string;
  error?: string | null;
  path_avatar?: string;
  avatar?: string;
} & Record<string, boolean | string | null>;

interface Props extends ExtendableStyledComponent {
  onError?: (err: ResponseError<DefaultResponseError>) => void;
  onSuccess?: () => void;
  mobile?: boolean;
}

export const MyProfileForm: React.FC<Props> = ({
  onSuccess,
  onError,
  mobile = false,
}) => {
  const [initialValues, setInitialValues] = useState<
    FormValues & Record<string, boolean | string | null>
  >({
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
  });
  const { t } = useTranslation();
  const { getFieldTranslations, filterByKey } =
    useAdditionalFieldTranslations();
  const {
    updateProfile,
    fields,
    fetchFields,
    user,
    updateAvatar,
    fetchProfile,
  } = useContext(UlamsContext);

  const isFetching = user.loading;

  useEffect(() => {
    fetchProfile();
    fetchFields({ class_type: "App\\Models\\User" });
  }, []);

  useEffect(() => {
    if (!user.loading && user.value) {
      setInitialValues((prevState) =>
        Object.assign({}, prevState, { ...user.value })
      );
    }
  }, [user]);

  useEffect(() => {
    const additionalFields = (fields && fields.list) || [];

    setInitialValues((prevState) => {
      return {
        ...prevState,
        ...additionalFields
          .filter(({ name }) => !(name in prevState))
          .reduce(
            (obj, item: API.Metadata) => ({
              ...obj,
              [item.name]: item.type === "boolean" ? false : item.default,
            }),
            {}
          ),
      };
    });
  }, [fields]);

  const isAdditionalRequiredField = useCallback(
    (field: API.Metadata) => {
      if (
        field?.rules &&
        field?.rules.length > 0 &&
        !!(field.rules as string[])?.find((rule) => rule === "required")
      ) {
        return true;
      }
      return false;
    },
    [fields]
  );

  const onAvatarChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      if (e.target.files && e.target.files[0]) {
        updateAvatar(e.target.files[0]);
      }
    },
    [updateAvatar]
  );

  return (
    <>
      <div
      className={`ulams-component ${styles.root} ${mobile ? styles.mobile : ""}`}
    >
        <div
        className={`ulams-component ${styles.formHeader} ${mobile ? styles.mobile : ""}`}
      >
          <Text size="18">{t("MyProfileForm.Avatar")}</Text>
          <Upload
            path={initialValues.path_avatar}
            url={initialValues.avatar}
            accept="image/*"
            onChange={onAvatarChange}
          />
        </div>
        <Formik
          enableReinitialize
          initialValues={initialValues}
          validate={(values) => {
            const errors: FormikErrors<FormValues & Record<string, string>> =
              {};

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
            updateProfile({
              ...values,
            })
              .then(() => {
                onSuccess && onSuccess();
              })
              .catch((err: ResponseError<DefaultResponseError>) => {
                // reset form to previous state only if error occured
                resetForm();
                setErrors({ error: err.data?.message, ...err.data.errors });
                onError && onError(err);
              })
              .finally(() => {
                setSubmitting(false);
                fetchProfile();
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
                label={t<string>("First name")}
                type="text"
                name="first_name"
                onChange={handleChange}
                onBlur={handleBlur}
                value={values.first_name}
                error={touched.first_name && errors.first_name}
                required
              />

              <Input
                label={t<string>("Last name")}
                type="text"
                name="last_name"
                onChange={handleChange}
                onBlur={handleBlur}
                value={values.last_name}
                error={touched.last_name && errors.last_name}
                required
              />

              <Input
                label={t<string>("Email")}
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
                label={t<string>("Phone")}
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

                  return field.type !== "boolean" && !r;
                })
                // NOTE: this is old filtering im not sure we should have diffrent filter for register and edit form this is for consideration
                // .filter(
                //   (field: API.Metadata) =>
                //     field.type === "varchar" || field.type === "text"
                // )
                .map((field: API.Metadata, index: number) =>
                  field.type === "varchar" ? (
                    <Input
                      key={`${field}${index}`}
                      required={isAdditionalRequiredField(field)}
                      label={
                        getFieldTranslations(field) ||
                        t(`AdditionalFields.${field.name}`)
                      }
                      type="text"
                      name={field.name}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      value={String(values[field.name]) || ""}
                      error={errors[field.name] && touched[field.name]}
                    />
                  ) : (
                    <TextArea
                      rows={10}
                      key={`${field}${index}`}
                      required={isAdditionalRequiredField(field)}
                      label={
                        getFieldTranslations(field) ||
                        t(`AdditionalFields.${field.name}`)
                      }
                      name={field.name}
                      onChange={handleChange}
                      onBlur={handleBlur}
                      value={String(values[field.name]) || ""}
                      error={errors[field.name] && touched[field.name]}
                    />
                  )
                )}

              {(fields.list || [])
                // .filter((field: API.Metadata) => field.type === "boolean")
                .filter((field: API.Metadata) => {
                  const r = filterByKey(field);

                  return field.type === "boolean" && !r;
                })
                .map((field: API.Metadata, index: number) => (
                  <Checkbox
                    checked={!!values[field.name]}
                    key={`${field.id}${index}`}
                    label={
                      getFieldTranslations(field) ||
                      t(`AdditionalFields.${field.name}`)
                    }
                    id={field.name + Date.now()}
                    name={field.name}
                    onChange={handleChange}
                    onBlur={handleBlur}
                    required={isAdditionalRequiredField(field)}
                  />
                ))}

              <Button
                mode="secondary"
                type="submit"
                loading={isSubmitting || isFetching}
                block
              >
                {t<string>("MyProfileForm.Update")}
              </Button>
            </form>
          )}
        </Formik>{" "}
      </div>
    </>
  );
};

export default MyProfileForm;
