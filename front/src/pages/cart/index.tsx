import React, { ReactNode, useContext, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { Elements } from "@stripe/react-stripe-js";
import { loadStripe } from "@stripe/stripe-js";
import StripeContent from "@/components/Cart/CartContent/stripe";
import { UlamsContext } from "@ulams/sdk/react";
import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import { FONTS } from "@ulams/components/theme/cssVars";
import Przelewy24Content from "@/components/Cart/CartContent/p24";
import usePayment from "@/hooks/usePayment";

import styles from "./cart.module.css";

enum PaymentGateway {
  Stripe = "Stripe",
  Przelewy24 = "Przelewy24",
}

type Props = {
  children?: ReactNode;
};

const CartPage: React.FC<Props> = () => {
  const { config } = useContext(UlamsContext);
  const { t } = useTranslation();
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const stripeConfigs: any = config?.value?.ulams_payments?.drivers;
  const stripeKey: string | undefined = stripeConfigs?.stripe?.publishable_key;
  // Load Stripe.js once per key; without a key Stripe() throws and the whole cart crashes.
  const stripePromise = useMemo(
    () => (stripeKey ? loadStripe(stripeKey) : null),
    [stripeKey]
  );
  // Stripe Elements loads the body font itself, so it needs the raw font links.
  const theme = useThemeTokens();
  const fontKey = theme?.bodyFont ?? theme?.font;
  const fontLinks = (fontKey && FONTS[fontKey]?.links) || [];

  const { defaultGateway } = usePayment();

  if (defaultGateway === PaymentGateway.Przelewy24) {
    return (
      <div className={styles.wrapper}>
        <Przelewy24Content />
      </div>
    );
  }

  if (defaultGateway === PaymentGateway.Stripe && !stripeKey) {
    return (
      <div className={styles.wrapper}>
        <p role="status" className={styles.notConfigured}>
          {t(
            "Cart.PaymentsNotConfigured",
            "Online payments are not configured for this site yet. Please contact the site administrator."
          )}
        </p>
      </div>
    );
  }

  if (defaultGateway === PaymentGateway.Stripe && stripeKey) {
    return (
      <div className={styles.wrapper}>
        <Elements
          stripe={stripePromise}
          options={{
            fonts: [
              {
                cssSrc: fontLinks[0],
              },
            ],
          }}
        >
          <StripeContent stripeKey={stripeKey} />
        </Elements>
      </div>
    );
  }
};

export default CartPage;
