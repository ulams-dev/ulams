import { useMemo, useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react/context";

export const useRoles = () => {
  const { user } = useContext(UlamsContext);

  const isTutor = useMemo(() => !!user.value?.roles?.includes("tutor"), [user]);
  const isStudent = useMemo(
    () => !!user.value?.roles?.includes("student"),
    [user]
  );

  return {
    isTutor,
    isStudent,
  };
};
