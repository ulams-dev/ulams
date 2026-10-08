import { useContext } from "react";
import { isMobile } from "react-device-detect";
import { UlamsContext } from "@ulams/sdk/react";
import { PricingCard } from "@ulams/components/components/atoms/PricingCard/PricingCard";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import styles from "./WebinarSidebar.module.css";
import { IconText } from "@ulams/components/components/atoms/IconText/IconText";
import { IconCamera, IconSquares } from "../../../../icons";
import { useTranslation } from "react-i18next";
import WebinarSidebarButtons from "./Buttons";
import ProductPrices from "@/components/ProductPrices";

const WebinarSidebar = () => {
  const {
    webinar: { value: webinarObject },
  } = useContext(UlamsContext);
  const { t } = useTranslation();

  return (
    <div className={styles.root} data-mobile={isMobile}>
      <PricingCard mobile={isMobile}>
        <Title level={4} as="h2">
          {webinarObject?.name}
        </Title>
        {/* PRICE */}
        <ProductPrices
          price={webinarObject?.product?.price}
          taxRate={webinarObject?.product?.tax_rate}
          oldPrice={webinarObject?.product?.price_old || undefined}
        />
        {/* BUTTONS */}
        <WebinarSidebarButtons />
        {/* FOOTER */}
        <div className="pricing-card-features">
          {/* {webinarObject?.place && (
          <IconText icon={<IconLocation />} text={`${webinarObject?.place}`} />
        )} */}
          {webinarObject?.duration && (
            <IconText
              icon={<IconCamera />}
              text={`${t("Duration")}: ${webinarObject.duration} ${
                webinarObject.duration === "1" ? t("Hour") : t("Hours")
              }`}
            />
          )}
          {webinarObject?.users_count ? (
            <IconText
              icon={<IconSquares />}
              text={`${t("Students")}: ${webinarObject?.users_count}`}
            />
          ) : (
            ""
          )}
        </div>
      </PricingCard>
    </div>
  );
};

export default WebinarSidebar;
