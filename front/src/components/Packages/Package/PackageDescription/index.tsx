import { useContext } from "react";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react/context";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
const PackageDescription = () => {
  const { product } = useContext(UlamsContext);
  const { t } = useTranslation();
  const description = product.value?.description;

  if (!description) {
    return null;
  }
  return (
    <section className="package-description-short with-border">
      <Title level={4}>{t("PackagePage.DescriptionTitle")}</Title>
      <Text>{description}</Text>
    </section>
  );
};

export default PackageDescription;
