import { SetStateAction, useContext, useEffect, useState } from "react";
import { UlamsContext } from "@ulams/sdk/react/context";
import { Link, useHistory } from "react-router-dom";
import Layout from "@/components/_App/Layout";
import { isMobile } from "react-device-detect";
import { useLocation } from "react-router-dom";
import { Title } from "@ulams/components/components/atoms/Typography/Title";

import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { RegisterForm } from "@ulams/components/components/organisms/RegisterForm/RegisterForm";
import { useTranslation } from "react-i18next";
import styles from "./RegisterPage.module.css";
import { MarkdownRenderer } from "@ulams/components/components/molecules/MarkdownRenderer/MarkdownRenderer";
import { Modal } from "@ulams/components/components/atoms/Modal/Modal";
import { Button } from "@ulams/components/components/atoms/Button/Button";

import { Link as LinkComponent } from "@ulams/components/components/atoms/Link/Link";
import Container from "@/components/Common/Container";
import routeRoutes from "@/components/Routes/routes";
import { EmailActivationImg } from "@/icons/index";
import { APP_URL } from "@/config/index";
import { redirectPrefix } from "@/utils/router";
import { isMobilePlatform } from "@/utils/index";
import { metaDataKeys } from "@/utils/meta";

const RegisterPage = () => {
  const { search } = useLocation();
  const { user, socialAuthorize } = useContext(UlamsContext);
  const [view, setView] = useState<"" | "success" | "register">("");
  const [modalVisible, setModalVisible] = useState(false);
  const { settings } = useContext(UlamsContext);

  const [email, setEmail] = useState<string>("");
  const history = useHistory();
  const token = search.split("?token=")[1];
  const { t } = useTranslation();

  const footerFromApi: string =
    settings?.value?.config?.[metaDataKeys.registerWarningMetaKey];

  const fieldLabels = {
    "AdditionalFields.Privacy Policy": (
      <Text size="14">
        {t("AcceptCheckbox")}{" "}
        <Link className={styles.link} to={routeRoutes.privacyPolicy}>
          {t("PrivacyPolicy")}
        </Link>
      </Text>
    ),
    "AdditionalFields.Terms of Service": (
      <Text size="14">
        {t("AcceptCheckbox")}{" "}
        <Link className={styles.link} to={routeRoutes.privacyPolicy}>
          {t("TermsOfService")}
        </Link>
      </Text>
    ),
  };
  if (token) {
    socialAuthorize(token);
    setTimeout(() => {
      history.push(routeRoutes.home);
    }, 1000);
  }

  if (!user.loading && !token && user.value) {
    history.push(routeRoutes.home);
  }

  useEffect(() => {
    setModalVisible(view === "success");
  }, [view]);

  const EmailActivation = () => {
    const { config } = useContext(UlamsContext);

    const accountActivationByAdmin =
      config?.value?.ulams_auth?.account_must_be_enabled_by_admin ===
      "enabled";

    return (
      <div className={styles.content}>
        <Container>
          <div className={styles.imageWrapper}>
            <EmailActivationImg />
          </div>

          <div className={styles.contentContainer}>
            <Title className={styles.emailTitle} level={3}>
              {t(
                `EmailActivation.${
                  accountActivationByAdmin ? "Title2" : "Title"
                }`
              )}
            </Title>
            {!accountActivationByAdmin && (
              <MarkdownRenderer
                components={{
                  a: (props) => <span>{props.children}</span>,
                }}
              >
                {t("EmailActivation.Text", { email })}
              </MarkdownRenderer>
            )}

            <MarkdownRenderer>
              {t(
                `EmailActivation.${
                  accountActivationByAdmin ? "HelpText2" : "HelpText"
                }`
              )}
            </MarkdownRenderer>
            {!accountActivationByAdmin && (
              <div className={styles.backText}>
                <LinkComponent onClick={() => setView("register")}>
                  {t("EmailActivation.RegisterAgain")}
                </LinkComponent>
              </div>
            )}
            <div className={styles.backToLogin}>
              <Button onClick={() => history.push(routeRoutes.login)}>
                {t("ResetForm.BackToLogin")}
              </Button>
            </div>
          </div>
        </Container>
      </div>
    );
  };

  return (
    <Layout metaTitle={t("LoginAndRegister")}>
      {footerFromApi && (
        <Modal
          className={styles.modal}
          onClose={() => setModalVisible(false)}
          visible={modalVisible}
          animation="zoom"
          maskAnimation="fade"
          destroyOnClose={true}
          width={800}
        >
          <Title level={4} className="modal-title">
            {t("Warning")}
          </Title>
          <MarkdownRenderer>{footerFromApi}</MarkdownRenderer>
          <Button mode="outline" onClick={() => setModalVisible(false)}>
            {t("I'm aware")}
          </Button>
        </Modal>
      )}

      {view !== "success" ? (
        <div className={styles.registerPage}>
          <Container>
            <RegisterForm
              return_url={`${APP_URL}${redirectPrefix()}${
                routeRoutes.emailVerify
              }`}
              fieldLabels={fieldLabels}
              mobile={isMobile}
              onLoginLink={() => history.push(routeRoutes.login)}
              onSuccess={(
                _: unknown,
                values: { email: SetStateAction<string> }
              ) => {
                setView("success");
                setEmail(values.email);
              }}
              {...(isMobilePlatform
                ? { submitText: "Załóż darmowe konto" }
                : {})}
            />
          </Container>
        </div>
      ) : (
        <EmailActivation />
      )}
    </Layout>
  );
};

export default RegisterPage;
