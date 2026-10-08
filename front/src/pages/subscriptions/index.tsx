import Container from "@/components/Common/Container";
import ActiveSubscription from "@/components/Subscriptions/ActiveSubscription";
import SubscriptionBox from "@/components/Subscriptions/Box";
import ContentLoader from "@/components/_App/ContentLoader";
import Layout from "@/components/_App/Layout";
import useSubscriptions from "@/hooks/useSubscriptions";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { UlamsContext } from "@ulams/sdk/react";
import { useContext } from "react";
import { isMobile } from "react-device-detect";
import { Col, Row } from "react-grid-system";
import { useTranslation } from "react-i18next";

import styles from "./subscriptions.module.css";

const SubscriptionsPage = () => {
  const { t } = useTranslation();
  const {
    subscriptions,
    isLoading,
    getActiveSubscription,
    subscriptionCancel,
  } = useSubscriptions();
  const { user } = useContext(UlamsContext);

  return (
    <Layout metaTitle={t("Subscriptions.Subs")}>
      <div className={styles.wrapper}>
        <Container>
          <Title level={1}>{t("Subscriptions.Subs")}</Title>
          <Text size="16">{t("Subscriptions.Text")}</Text>
          {getActiveSubscription && user.value?.id && (
            <ActiveSubscription
              activeSubscription={getActiveSubscription}
              subscriptionCancel={subscriptionCancel}
            />
          )}
          {!getActiveSubscription?.id && (
            <div
              className={styles.subscriptionsContainer}
              data-mobile={isMobile}
            >
              {isLoading && <ContentLoader />}
              {!isLoading && (
                <Row>
                  {subscriptions.length ? (
                    subscriptions.map((subscription) => (
                      <Col lg={6} md={12} key={subscription.id}>
                        <SubscriptionBox subscription={subscription} />
                      </Col>
                    ))
                  ) : (
                    <Text>{t("MyProfilePage.NoSub")}</Text>
                  )}
                </Row>
              )}
            </div>
          )}
        </Container>
      </div>
    </Layout>
  );
};
export default SubscriptionsPage;
