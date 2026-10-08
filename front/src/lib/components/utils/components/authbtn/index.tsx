import { UlamsContext } from "@ulams/sdk/react";
import { useContext } from "react";
import { Button, Spin } from "../../../";

const Authbtn = () => {
  const { login, user } = useContext(UlamsContext);
  return (
    <Button
      mode="secondary"
      onClick={() => {
        login({ email: "student@ulams.app", password: "secret" });
      }}
    >
      {user.loading && <Spin />}
      authorize to see component
    </Button>
  );
};

export default Authbtn;
