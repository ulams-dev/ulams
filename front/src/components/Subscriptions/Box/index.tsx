import { Purchases } from "@revenuecat/purchases-capacitor";
import { useCallback, useEffect, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { useHistory } from "react-router-dom";
import { Capacitor } from "@capacitor/core";
import routeRoutes from "@/components/Routes/routes";
import { VITE_APP_ANDROID_APIKEY, VITE_APP_IOS_APIKEY } from "@/config/index";
import usePayment from "@/hooks/usePayment";
import { StarIcon } from "@/icons/index";
import { formatPrice, isMobilePlatform } from "@/utils/index";
import {
  findProductByIdentifier,
  getRevenuecatIdForSubscription,
  revenuecatErrorHandler,
} from "@/utils/payment";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { CapacitorPaymentError } from "@/types/index";
import styles from "./Box.module.css";

type Props = {
  // TODO: when model types will be updated change this
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  subscription: any;
};

const SubscriptionBox: React.FC<Props> = ({ subscription }) => {
  const { t } = useTranslation();
  const { buySubscriptionByP24, user } = usePayment();

  const history = useHistory();
  const showTag = useMemo(() => {
    return subscription.tags && subscription.tags.includes("best-deal");
  }, [subscription.tags]);

  const handleBuySubscription = useCallback(() => {
    if (user.value?.id) {
      buySubscriptionByP24(subscription.id);
    } else {
      history.push(routeRoutes.login);
    }
  }, [subscription.id, user.value?.id, buySubscriptionByP24, history]);

  useEffect(() => {
    (async function () {
      const id = user?.value?.id;

      if (Capacitor.getPlatform() === "ios") {
        await Purchases.configure({
          apiKey: VITE_APP_IOS_APIKEY,
          appUserID: `${id}`,
        });
      } else if (Capacitor.getPlatform() === "android") {
        await Purchases.configure({
          apiKey: VITE_APP_ANDROID_APIKEY,
          appUserID: `${id}`,
        });
      }
    })();
  }, [user?.value?.id]);

  const buyOnMobile = useCallback(async () => {
    if (!user.value?.id) {
      history.push(routeRoutes.login);
      return;
    }
    const id = getRevenuecatIdForSubscription(subscription);
    const offerings = await Purchases.getOfferings();
    const packages = offerings?.current?.availablePackages || [];

    const product = findProductByIdentifier(packages, id);

    if (product) {
      try {
        await Purchases.purchaseStoreProduct({
          product: product,
        });
        // Redirect to course page
        history.push(routeRoutes.home);
      } catch (error) {
        revenuecatErrorHandler(error as CapacitorPaymentError);
      }
    }
  }, [history, subscription, user.value?.id]);

  return (
    <div
      className={`${styles.subscription}${
        isMobilePlatform ? ` ${styles.mobile}` : ""
      }`}
    >
      <div className={styles.content}>
        {showTag && (
          <div className={styles.tag}>
            <Text size="13">{t("Subscriptions.CheapestOffer")}</Text>
          </div>
        )}

        <Text>{t("Subscriptions.AccessVia")}</Text>
        <Title level={1} as={"h4"}>
          {subscription.subscription_duration}{" "}
          {t(`Subscriptions.Periods.${subscription.subscription_period}`)}
        </Title>
        <Text className={styles.information} size="13">
          <StarIcon /> {subscription.trial_duration}-
          {t(`Subscriptions.Periods.${subscription.trial_period}`)}{" "}
          {t("Subscriptions.TrialText")}
        </Text>
        <div className={styles.divider}></div>
        <Text size="13" className={styles.description}>
          {subscription.name}
        </Text>
        <Text size="24" className={styles.price} bold>
          {formatPrice(subscription.gross_price)} zł
        </Text>
        {
          <Button
            mode="secondary"
            onClick={() => {
              if (isMobilePlatform) {
                buyOnMobile();
                return;
              }
              handleBuySubscription();
            }}
          >
            {t("Subscriptions.IPick")}
          </Button>
        }
      </div>
    </div>
  );
};

export default SubscriptionBox;
