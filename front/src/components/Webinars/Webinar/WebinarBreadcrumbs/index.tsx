import { useContext } from "react";
import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import Breadcrumbs from "@/components/Common/Breadcrumbs";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import routeRoutes from "@/components/Routes/routes";

const WebinarBreadcrumbs = () => {
  const { webinar } = useContext(UlamsContext);
  const { t } = useTranslation();

  if (!webinar.value) {
    return null;
  }
  return (
    <Breadcrumbs
      items={[
        <Link to={routeRoutes.home}>{t("Home")}</Link>,
        <Link to={routeRoutes.webinars}>{t("Menu.Webinars")}</Link>,
        <Text size="12">{webinar.value.name}</Text>,
      ]}
    />
  );
};

export default WebinarBreadcrumbs;
