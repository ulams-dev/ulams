import { useContext, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { Link } from "react-router-dom";
import { UlamsContext } from "@ulams/sdk/react/context";
import Container from "@/components/Common/Container";

import routeRoutes from "@/components/Routes/routes";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { ThankYouIcon } from "@/icons/index";
import { isMobile } from "react-device-detect";
import styles from "./CartSuccess.module.css";

const CartSuccess = () => {
  const { t } = useTranslation();

  const { fetchProgress } = useContext(UlamsContext);

  useEffect(() => {
    fetchProgress();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className={isMobile ? styles.mobile : undefined}>
      <Container>
        <div className={`cart-success-container ${styles.container}`}>
          <ThankYouIcon />
          <Title level={2}>{t("Cart.ThankYouTitle")}</Title>
          <div>
            <Text size="16" className="cart-success-text">
              {t("Cart.ThankYouText")}
            </Text>
          </div>
          <div>
            <Link to={routeRoutes.myProfile}>
              <Text size="16">{t("Navbar.MyCourses")}</Text>
            </Link>
            <Link to={routeRoutes.myConsultations}>
              <Text size="16">{t("Navbar.MyConsultations")}</Text>
            </Link>
          </div>
          <div>
            <Text size="16">{t("Cart.Status")}</Text>{" "}
            <Link to={routeRoutes.myOrders}>
              <Text size="16">{t("Navbar.MyOrders")}</Text>
            </Link>
          </div>
        </div>
      </Container>
    </div>
  );
};

export default CartSuccess;
