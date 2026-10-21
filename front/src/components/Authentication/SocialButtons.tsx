import { UlamsContext } from "@ulams/sdk/react/context";
import React, { ReactElement } from "react";
import { useContext } from "react";
import { routerType } from "@/utils/router";
import {
  SocialProvider,
  startSocialLoginUrl,
} from "@ulams/sdk/utils/socialLogin";

const SocialButtons: React.FC = (): ReactElement => {
  const { apiUrl } = useContext(UlamsContext);

  const isHashRouter = routerType() === "HashRouter";

  const urlSelf = `${window.location.origin}${
    isHashRouter ? "/%23" : ""
  }/authentication`;

  // the verifier stays in this browser; only its challenge goes to the API (ADR 0121)
  const start = (provider: SocialProvider) => async (e: React.MouseEvent) => {
    e.preventDefault();
    window.location.assign(await startSocialLoginUrl(apiUrl, provider, urlSelf));
  };

  return (
    <div className="social-btns">
      <a
        className="social-btns__button social-btns__button--blue"
        href={`${apiUrl}/api/auth/social/facebook`}
        onClick={start("facebook")}
      >
        {"Login with Facebook"}
      </a>

      <a
        className="social-btns__button social-btns__button--red"
        href={`${apiUrl}/api/auth/social/google`}
        onClick={start("google")}
      >
        {"Login with Google"}
      </a>
    </div>
  );
};

export default SocialButtons;
