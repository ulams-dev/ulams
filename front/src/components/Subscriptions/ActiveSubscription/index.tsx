import { useTranslation } from "react-i18next";
import { Text } from "@ulams/components/components/atoms/Typography/Text";

import { formatDate } from "@/utils/date";
import { StarIcon } from "@/icons/index";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { useCallback, useState } from "react";
import styles from "./ActiveSubscription.module.css";

enum SubscriptionStatus {
  ACTIVE = "active",
  CANCELED = "cancelled",
}

type Props = {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  activeSubscription: any;
  subscriptionCancel: (id: number) => void;
};

const ActiveSubscription: React.FC<Props> = ({
  activeSubscription,
  subscriptionCancel,
}) => {
  const [subStatus, setSubSatuts] = useState(
    activeSubscription?.status || SubscriptionStatus.ACTIVE
  );
  const { t } = useTranslation();

  const handleSubscriptionCancel = useCallback(() => {
    setSubSatuts(SubscriptionStatus.CANCELED);
    subscriptionCancel(activeSubscription.id);
  }, [activeSubscription?.id, subscriptionCancel]);

  return (
    <div className={`info-box ${styles.infoBox}`}>
      <div>
        {activeSubscription && <StarIcon />}
        <Text>
          {activeSubscription
            ? t("Subscriptions.ActiveSubscription", {
                date: formatDate(
                  // TODO: when ts models are ready, remove  this comment
                  // @ts-ignore
                  activeSubscription.end_date,
                  "dd.MM.yyyy"
                ),
              })
            : t("Subscriptions.NoSubscription")}
        </Text>
      </div>
      {subStatus === SubscriptionStatus.CANCELED && (
        <Text className={`${SubscriptionStatus.CANCELED} ${styles.cancelled}`}>
          {t("Subscriptions.Cancelled")}
        </Text>
      )}
      {activeSubscription && subStatus === SubscriptionStatus.ACTIVE && (
        <Button onClick={handleSubscriptionCancel}>Anuluj subskrypcję</Button>
      )}
    </div>
  );
};

export default ActiveSubscription;
