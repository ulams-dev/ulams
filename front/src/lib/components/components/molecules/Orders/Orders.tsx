import React, { ReactNode, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { Text } from "../../atoms/Typography/Text";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import styles from "./Orders.module.css";

interface OrdersProps
  extends React.InputHTMLAttributes<HTMLTableElement>,
    ExtendableStyledComponent {
  mobile?: boolean;
  data: {
    status: ReactNode;
    title: ReactNode;
    date: ReactNode;
    price: ReactNode;
    actions?: ReactNode;
  }[];
}

export const Orders: React.FC<OrdersProps> = (props) => {
  const { data, mobile = false, className = "" } = props;

  const { t } = useTranslation();

  const hasActions = useMemo(() => {
    return data.some((record) => !!record.actions);
  }, [data]);

  return (
    <div className={`ulams-component ${styles.root} ${className}`}>
      {data.length === 0 && <Text>{t("Orders.NoRecords")}</Text>}
      {data.length > 0 && (
        <React.Fragment>
          {!mobile && (
            <div className="labels-row">
              <div className="single-label">
                <Text size={"14"}>{t("Orders.Title")}</Text>
              </div>{" "}
              <div className="single-label">
                <Text size={"14"}>{t("Orders.Status")}</Text>
              </div>
              <div className="single-label">
                <Text size={"14"}>{t("Orders.Date")}</Text>
              </div>
              <div className="single-label">
                <Text size={"14"}>{t("Orders.Price")}</Text>
              </div>
              {hasActions && (
                <div className="single-label">
                  <Text size={"14"}>{t("Orders.Actions")}</Text>
                </div>
              )}
            </div>
          )}
          {data.map((record, i) => (
            <div
              key={i}
              className={`${styles.card} ${mobile ? styles.mobile : ""}`}
            >
              <div className="single-content">{record.title}</div>
              <div className="single-content">{record.status}</div>
              <div className="single-content">{record.date}</div>
              <div className="single-content">{record.price}</div>
              {hasActions && (
                <div className="single-content">{record.actions}</div>
              )}
            </div>
          ))}
        </React.Fragment>
      )}
    </div>
  );
};

export default Orders;
