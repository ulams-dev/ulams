import routeRoutes from "@/components/Routes/routes";
import useSubscriptions from "@/hooks/useSubscriptions";
import { formatPrice } from "@/utils/index";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { Button } from "@ulams/components";
import { API } from "@ulams/sdk";
import { UlamsContext } from "@ulams/sdk/react";
import { useCallback, useContext } from "react";
import { isMobile } from "react-device-detect";
import { Row, Col } from "react-grid-system";
import { useTranslation } from "react-i18next";
import { useHistory } from "react-router-dom";
import styles from "./styles.module.css";

type Props = {
  course: API.Course;
};

const ProductModal: React.FC<Props> = ({ course }) => {
  const { getCheapestSubscription } = useSubscriptions();
  const { cart, addToCart } = useContext(UlamsContext);

  const { t } = useTranslation();
  const { push } = useHistory();

  const handleBuyCourse = useCallback(() => {
    addToCart(Number(course.product?.id)).then(() => push(routeRoutes.cart));
  }, [course, addToCart, push]);

  return (
    <div className={styles.productModal}>
      <Title className={styles.modalHeader} level={2}>
        {t("Subscriptions.GetAccess")}
      </Title>
      <Text>{t("Subscriptions.YouHaveTwoOptions")}</Text>
      <Row>
        <Col lg={6} md={12} sm={12}>
          <div
            className={`product-box ${styles.productBox} ${
              isMobile ? styles.mobile : ""
            }`}
          >
            <Title className={styles.title} level={3}>
              {t("Buy Course")}
            </Title>
            <div className={styles.divider}></div>
            <Text className={styles.description} size={"13"}>
              {course.title}
            </Text>
            <Text className={styles.price} size="24" bold>
              {formatPrice(course.product?.gross_price)} zł
            </Text>
            <Button loading={cart.loading} onClick={() => handleBuyCourse()}>
              {t("Subscriptions.IPick")}
            </Button>
          </div>
        </Col>
        {getCheapestSubscription?.id && (
          <Col lg={6} md={12} sm={12}>
            <div
              className={`product-box ${styles.productBox} ${
                isMobile ? styles.mobile : ""
              }`}
            >
              <Title className={styles.title} level={3}>
                {getCheapestSubscription?.name}
              </Title>
              <div className={styles.divider}></div>
              <Text className={styles.description} size={"13"}>
                {getCheapestSubscription?.description}
              </Text>
              <Text className={styles.price} size="24" bold>
                {t("From")} {formatPrice(getCheapestSubscription?.gross_price)}{" "}
                zł
              </Text>
              <Button onClick={() => push(routeRoutes?.subscriptions)}>
                {t("Subscriptions.IPick")}
              </Button>
            </div>
          </Col>
        )}
      </Row>
    </div>
  );
};

export default ProductModal;
