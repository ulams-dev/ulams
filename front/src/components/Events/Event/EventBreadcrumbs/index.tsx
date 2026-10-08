import { useContext } from "react";
import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import Breadcrumbs from "@/components/Common/Breadcrumbs";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import routeRoutes from "@/components/Routes/routes";

const EventBreadcrumbs = () => {
  const { stationaryEvent } = useContext(UlamsContext);
  const { t } = useTranslation();

  if (!stationaryEvent.value) {
    return null;
  }
  return (
    <Breadcrumbs
      items={[
        <Link to={routeRoutes.home}>{t("Home")}</Link>,
        <Link to={routeRoutes.events}>{t("Menu.Events")}</Link>,
        <Text size="12">{stationaryEvent.value.name}</Text>,
      ]}
    />
  );
};

export default EventBreadcrumbs;
