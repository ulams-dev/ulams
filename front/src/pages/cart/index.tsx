import React, { ReactNode, useContext } from "react";
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
  const stripePromise = (publishable_key: string) =>
    loadStripe(publishable_key);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const stripeConfigs: any = config?.value?.ulams_payments?.drivers;
  const stripeKey = stripeConfigs?.stripe?.publishable_key;
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

  if (defaultGateway === PaymentGateway.Stripe) {
    return (
      <div className={styles.wrapper}>
        <Elements
          stripe={stripePromise(stripeKey)}
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
