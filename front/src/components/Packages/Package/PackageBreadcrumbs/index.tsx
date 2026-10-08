import { useContext } from "react";
import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import Breadcrumbs from "@/components/Common/Breadcrumbs";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import routeRoutes from "@/components/Routes/routes";

export const PackageBreadcrumbs = () => {
  const { product } = useContext(UlamsContext);
  const { t } = useTranslation();

  if (!product.value) {
    return null;
  }
  return (
    <Breadcrumbs
      items={[
        <Link to={routeRoutes.home}>{t("Home")}</Link>,
        <Link to={routeRoutes.packages}>{t("Menu.Packages")}</Link>,
        <Text size="12">{product.value.name}</Text>,
      ]}
    />
  );
};
