import React, { useContext, useEffect, useState, useCallback } from "react";
import { useLocation, useHistory } from "react-router-dom";
import Layout from "@/components/_App/Layout";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import { Spin } from "@ulams/components/components/atoms/Spin/Spin";
import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import routeRoutes from "@/components/Routes/routes";
import { ThankYouIcon } from "@/icons/index";
import styles from "./VerifyEmail.module.css";
import { Title } from "@ulams/components/components/atoms/Typography/Title";

const VerifyEmail: React.FC = () => {
  const { push } = useHistory();
  const { search } = useLocation();
  const id = search && search?.split("&")[0]?.split("=")[1];
  const hash = search && search?.split("&")[1]?.split("=")[1];
  const { t } = useTranslation();
  const theme = useThemeTokens();
  const { emailVerify } = useContext(UlamsContext);

  const [state, setState] = useState({
    loading: false,
    state: "init",
    isVerified: false,
    message: "",
  });

  const verifyEmail = useCallback(async () => {
    setState((prevState) => ({
      ...prevState,
      loading: true,
    }));

    try {
      const request =
        id && hash && (await emailVerify(String(id), String(hash)));

      if (request) {
        setState((prevState) => ({
          ...prevState,
          state: "success",
          message: request.message,
          isVerified: true,
        }));
      }
    } catch (error) {
      console.error(error);
    } finally {
      setState((prevState) => ({
        ...prevState,
        loading: false,
      }));
    }
  }, [id, hash, emailVerify]);

  useEffect(() => {
    if (!hash) {
      push(routeRoutes.home);
    }
    id && hash && verifyEmail();
  }, [id, hash, verifyEmail, push]);

  return (
    <Layout>
      <div className={`${styles.root} profile-authentication-area`}>
        <div className="container">
          <div className="row ">
            <div className="col-lg-12 col-md-12">
              <div className={styles.contentWrapper}>
                <ThankYouIcon />
                {state.loading && <Spin color={theme?.primaryColor} />}{" "}
                {state.isVerified && (
                  <Title level={2}>{t("EmailWasVerified")}</Title>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default VerifyEmail;
