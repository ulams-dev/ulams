import * as React from "react";
import { Title } from "../../atoms/Typography/Title";
import { ReactNode } from "react";

import { Button } from "../../atoms/Button/Button";
import styles from "./CheckoutCard.module.css";
import { t } from "i18next";
import { ExtendableStyledComponent } from "@ulams/components/types/component";

const IconBin = () => {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="12.727"
      height="14"
      viewBox="0 0 12.727 14"
    >
      <path
        id="_2638315_bin_delete_dust_erace_garbage_icon"
        data-name="2638315_bin_delete_dust_erace_garbage_icon"
        d="M12.182,14.727H4.545V5.818H3.273v8.718A1.454,1.454,0,0,0,4.545,16h7.636a1.454,1.454,0,0,0,1.273-1.464V5.818H12.182ZM10.273,3.273V2H6.455V3.273H2V4.545H14.727V3.273ZM6.455,7.091v6.364H7.727V7.091ZM9,7.091v6.364h1.273V7.091Z"
        transform="translate(-2 -2)"
        fill="#afafaf"
      />
    </svg>
  );
};
interface StyledCheckoutCardProps {
  mobile?: boolean;
}

interface CheckoutImgProps {
  src: string;
  alt: string;
}

export interface CheckoutCardProps
  extends StyledCheckoutCardProps,
    ExtendableStyledComponent {
  title: ReactNode;
  img: CheckoutImgProps | ReactNode;
  categories?: ReactNode;
  summary?: ReactNode[];
  price: string;
  oldPrice?: string;
  handleDelete: () => void;
}

export const CheckoutCard: React.FC<CheckoutCardProps> = (props) => {
  const {
    title,
    img,
    oldPrice,
    price,
    handleDelete,
    mobile = false,
    className = "",
    categories,
  } = props;

  const thumbnail = () => {
    return (
      <div className={`ulams-component checkout-card-img ${className}`}>
        <img
          src={(img as CheckoutImgProps).src}
          alt={(img as CheckoutImgProps).alt}
        />
      </div>
    );
  };

  return (
    <div
      className={`ulams-component ${styles.root} ${mobile ? styles.mobile : ""}`}
    >
      <div className="image-title">
        {thumbnail()}
        <div>
          {React.isValidElement(categories) && (
            <div className="categories">{categories}</div>
          )}
          <Title
            as={"h4"}
            level={4}
            style={{ marginBottom: mobile ? "23px" : "10px" }}
          >
            {title}
          </Title>
        </div>
      </div>

      <div className="action-price-wrapper">
        {
          <div className="price">
            {oldPrice && (
              <Title as={"h5"} level={5} className={"checkout-card-discount"}>
                {oldPrice}
              </Title>
            )}
            <Title as={"h4"} level={4}>
              {price}
            </Title>
          </div>
        }
        <div>
          <Button
            mode={"icon"}
            className={"checkout-card-remove"}
            onClick={handleDelete}
            onKeyDown={(e) => e.key === "Enter" && handleDelete()}
            aria-label={t("Actions.Remove")}
          >
            <IconBin />
          </Button>
        </div>
      </div>
    </div>
  );
};

export default CheckoutCard;
