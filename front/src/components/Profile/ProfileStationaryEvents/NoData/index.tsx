import { useTranslation } from "react-i18next";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { isMobile } from "react-device-detect";
import styles from "./styles.module.css";
import { useHistory } from "react-router-dom";
import routeRoutes from "@/components/Routes/routes";

const ProfileStationaryEventsNoData = () => {
  const { t } = useTranslation();
  const history = useHistory();
  return (
    <div className={`${styles.noData} ${isMobile ? styles.mobile : ""}`}>
      <Title level={3}>{t("MyProfilePage.EmptyEventTitle")}</Title>
      <Text className={styles.smallText}>
        {t("MyProfilePage.EmptyEventText")}
      </Text>
      <Button onClick={() => history.push(routeRoutes.events)} mode="secondary">
        {t("MyProfilePage.EmptyEventsBtnText")}
      </Button>
    </div>
  );
};

export default ProfileStationaryEventsNoData;
