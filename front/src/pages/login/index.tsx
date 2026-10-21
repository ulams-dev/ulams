import { useCallback, useContext, useState } from "react";
import { UlamsContext } from "@ulams/sdk/react/context";
import { useHistory } from "react-router-dom";
import Layout from "@/components/_App/Layout";
import { isMobile } from "react-device-detect";
import { useLocation } from "react-router-dom";
import { ResetPasswordForm } from "@ulams/components/components/organisms/ResetPasswordForm/ResetPasswordForm";
import { LoginForm } from "@ulams/components/components/organisms/LoginForm/LoginForm";
import { useTranslation } from "react-i18next";
import Container from "@/components/Common/Container";
import routeRoutes from "@/components/Routes/routes";
import AuthWrapper from "@/components/Authentication/AuthWrapper";
import { toast } from "@/utils/toast";
import { APP_URL } from "@/config/index";
import { redirectPrefix } from "@/utils/router";
import { isMobilePlatform } from "@/utils/index";
import { useSocialCallback } from "@/hooks/useSocialCallback";

const Login = () => {
  const { search, state } = useLocation<{ referrer?: string }>();
  const { user } = useContext(UlamsContext);
  const [view, setView] = useState<
    "login" | "forgotPassword" | "register" | "success"
  >("login");
  const { t } = useTranslation();
  const history = useHistory();
  const referrer =
    (state && state.referrer) || search.split("?referrer=")[1] || "/";

  // social login: the redirect carries a one-time code, never a token (ADR 0121)
  const onSocialDone = useCallback(
    (ok: boolean) => {
      if (ok) history.push(referrer);
      else toast(t("UnexpectedError"), "error");
    },
    [history, referrer, t]
  );
  const finishingSocialLogin = useSocialCallback(search, onSocialDone);

  if (!user.loading && !finishingSocialLogin && user.value) {
    history.push(referrer);
  }

  return (
    <Layout metaTitle={t("LoginAndRegister")}>
      <AuthWrapper>
        <Container>
          {view === "login" ? (
            <LoginForm
              onResetPasswordLink={() => setView("forgotPassword")}
              onRegisterLink={() => history.push(routeRoutes.register)}
              mobile={isMobile}
              {...(isMobilePlatform
                ? { submitText: "Załóż darmowe konto" }
                : {})}
            />
          ) : (
            <ResetPasswordForm
              mobile={isMobile}
              backToLogin={() => setView("login")}
              onRegisterLink={() => history.push(routeRoutes.register)}
              return_url={`${APP_URL}${redirectPrefix()}${routeRoutes.reset}`}
              onFirstStepSuccess={() =>
                toast(t("LoginPage.ForgotSuccess"), "success")
              }
              onFirstStepError={() => toast(t("UnexpectedError"), "error")}
            />
          )}
        </Container>
      </AuthWrapper>
    </Layout>
  );
};

export default Login;
