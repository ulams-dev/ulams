import React, { useContext } from "react";
import { Route, Redirect, RouteProps } from "react-router-dom";
import { UlamsContext } from "@ulams/sdk/react/context";
import routes from "./routes";

const PrivateRoute: React.FC<RouteProps> = ({
  component: Component,
  ...rest
}: // eslint-disable-next-line
any) => {
  const { login } = routes;
  const { user } = useContext(UlamsContext);

  const referrer = (rest.location && rest.location.pathname) || rest.path;

  return (
    <Route
      {...rest}
      render={(props) =>
        user.value && user.value.id ? (
          <Component {...props} />
        ) : (
          <Redirect
            to={{
              pathname: login,
              search: `?referrer=${referrer}`,
              state: { referrer: referrer },
            }}
          />
        )
      }
    />
  );
};

export default PrivateRoute;
