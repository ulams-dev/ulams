import React, { useMemo } from "react";
import { Input } from "@ulams/components/components/atoms/Input/Input";
import {
  CardCvcElement,
  CardExpiryElement,
  CardNumberElement,
} from "@stripe/react-stripe-js";
import { useTranslation } from "react-i18next";
import { Col, Row } from "react-grid-system";
import { FONTS } from "@ulams/components/theme/cssVars";
import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import styles from "./PaymentForm.module.css";

type Props = {
  billingDetails: {
    name: string;
  };
  setBillingDetails: ({ name }: { name: string }) => void;
};

const PaymentForm: React.FC<Props> = ({
  billingDetails,
  setBillingDetails,
}) => {
  const theme = useThemeTokens();
  const { t } = useTranslation();
  const font = (FONTS[theme?.font ?? "Inter"] ?? FONTS.Inter).fontFamily;
  const fontFamily = font.split(",")[0].replace(/['"]/g, "");
  const isDark = theme?.mode === "dark";
  const gray1 = theme?.gray1 ?? "#4A4A4A";
  const white = theme?.white ?? "#FFFFFF";

  const options = useMemo(() => {
    return {
      style: {
        base: {
          fontFamily: fontFamily,
          backgroundColor: "transparent",
          padding: "11px 12px 13px",
          border: `1px solid red`,
          color: isDark ? white : gray1,
          fontSize: "12px",
          "::placeholder": {
            color: isDark ? "#c3c3c3" : gray1,
          },
        },
        invalid: {
          color: "#ff0000",
        },
      },
    };
  }, [fontFamily, isDark, gray1, white]);

  return (
    <div className={styles.form}>
      <Row>
        <Col lg={6}>
          <div className="input-wrapper--custom">
            <Input
              label={t<string>("Cart.FullName")}
              type="text"
              onChange={(e) =>
                setBillingDetails({
                  ...billingDetails,
                  name: e.currentTarget.value,
                })
              }
              value={billingDetails.name}
            />
          </div>
        </Col>
        <Col lg={6}>
          <div className="input-wrapper">
            <CardNumberElement options={options} id="cardNumber" />
            <label className={styles.label} htmlFor="cardNumber">
              {t<string>("Card number")}
            </label>
          </div>
        </Col>
      </Row>
      <Row>
        <Col lg={6}>
          <div className="input-wrapper">
            <CardExpiryElement options={options} id="cardExpiry" />
            <label className={styles.label} htmlFor="cardExpiry">
              {t<string>("Expiration date")}
            </label>
          </div>
        </Col>
        <Col lg={6}>
          <div className="input-wrapper">
            <CardCvcElement options={options} id="cardCVC" />
            <label className={styles.label} htmlFor="cardCVC">CVC</label>
          </div>
        </Col>
      </Row>
    </div>
  );
};

export default PaymentForm;
