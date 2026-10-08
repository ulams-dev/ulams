import React, { useContext } from "react";
import { Route, Redirect, RouteProps } from "react-router-dom";
import { UlamsContext } from "@ulams/sdk/react/context";
import routes from "./routes";

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

  const fullVisibility =
    config?.value?.ulams_courses?.course_visibility === "show_all" || false;

  return (
    <Route
      {...rest}
      render={(props) =>
        (platformVisibility && !(user.value && user.value.id)) ||
        (user.value && user.value.id && fullVisibility) ? (
          <Component {...props} />
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
