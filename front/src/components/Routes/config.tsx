import React, { useContext } from "react";
import { Route, Redirect, RouteProps } from "react-router-dom";
import { UlamsContext } from "@ulams/sdk/react/context";
import routes from "./routes";
import { Loader } from "../_App/Loader/Loader";

const ConfigRoute: React.FC<RouteProps> = ({
  component: Component,
  ...rest
}: // eslint-disable-next-line
any) => {
  const { login } = routes;
  const { user, fetchConfig, config } = useContext(UlamsContext);

  React.useEffect(() => {
    fetchConfig();
  }, [fetchConfig]);

  const platformVisibility =
    config?.value?.ulams_courses?.platform_visibility === "public" || false;

  // On a fresh visit the config is not loaded yet; wait for it instead of
  // redirecting a deep link (e.g. a landing page's course link) to the login page.
  const seenLoading = React.useRef(false);
  if (config?.loading) seenLoading.current = true;
  const configKnown =
    !!config?.value?.ulams_courses ||
    !!config?.error ||
    (seenLoading.current && !config?.loading);

  return (
    <Route
      {...rest}
      render={(props) =>
        platformVisibility || (user.value && user.value.id) ? (
          <Component {...props} />
        ) : !configKnown ? (
          <Loader />
        ) : (
          <Redirect
            to={{
              pathname: login,
            }}
          />
        )
      }
    />
  );
};

export default ConfigRoute;
