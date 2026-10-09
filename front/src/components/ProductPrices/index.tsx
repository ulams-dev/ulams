import { formatPrice } from "@/utils/index";
import React from "react";
import {
  Text,
  TextSize,
} from "@ulams/components/components/atoms/Typography/Text";
import styles from "./ProductPrices.module.css";
import { isMobile } from "react-device-detect";
import { useTranslation } from "react-i18next";

type Sizes = {
  old: TextSize;
  new: TextSize;
};

type Props = {
  price?: number;
  taxRate?: number;
  oldPrice?: number | null;
  textSizes?: Sizes;
  isFree?: boolean;
};

const ProductPrices: React.FC<Props> = ({
  price,
  taxRate,
  oldPrice,
  textSizes,
  isFree,
}) => {
  const { t } = useTranslation();
  if (isMobile) {
    return (
      <div className={styles.prices}>
        {oldPrice && (
          <div className="pricing-card-discount">
            <Text size={textSizes?.old || "18"}>
              {formatPrice(oldPrice, taxRate)} zł
            </Text>
          </div>
        )}
        <Text size={textSizes?.new || "16"}>
          {formatPrice(price, taxRate)} zł
        </Text>
      </div>
    );
  }
  if (isFree) {
    return (
      <div className={styles.prices}>
        <Text size={textSizes?.new || "16"}>{t("CoursesPage.Free")}</Text>
      </div>
    );
  }
  return (
    <div className={styles.prices}>
      <Text size={textSizes?.new || "16"}>
        {formatPrice(price, taxRate)} zł
      </Text>
      {oldPrice && (
        <div className="pricing-card-discount">
          <Text size={textSizes?.old || "18"}>
            {formatPrice(oldPrice, taxRate)} zł
          </Text>
        </div>
      )}
    </div>
  );
};

export default ProductPrices;
