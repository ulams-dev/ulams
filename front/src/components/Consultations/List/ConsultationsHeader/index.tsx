import { useTranslation } from "react-i18next";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import ConsultationsHeaderFilters from "./ConsultationsFilter";
import { isMobile } from "react-device-detect";
import { useSearchParams } from "../../../../hooks/useSearchParams";
import styles from "./styles.module.css";

const ConsultationsHeader = () => {
  const { t } = useTranslation();
  const { paramsToObject } = useSearchParams();
  const showTags = !!paramsToObject && Object.keys(paramsToObject).length > 2;

  return (
    <div
      className={`${styles.header} ${isMobile ? styles.mobile : ""}`}
      data-show-tags={showTags}
    >
      <Title level={1}> {t("Menu.Consultations")}</Title>
      <ConsultationsHeaderFilters />
    </div>
  );
};

export default ConsultationsHeader;
