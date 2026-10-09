import { Certificate } from "@ulams/sdk/types";
import React from "react";
import styles from "./CertificateCard.module.css";

type Props = {
  certificate?: Certificate;
  uptitle: React.ReactNode;
  title: React.ReactNode;
  dateUptitle: React.ReactNode;
  date: React.ReactNode;
  actions?: React.ReactNode;
};

export const CertificateCard: React.FC<Props> = ({
  uptitle,
  title,
  dateUptitle,
  date,
  actions,
}) => {
  return (
    <div className={`${styles.root} certificate-card`}>
      <div className="title-wrapper">
        <div className="title-wrapper__uptitle">{uptitle}</div>
        {title}
      </div>
      <div className="date-wrapper">
        <div className="title-wrapper__uptitle">{dateUptitle}</div>
        {date}
      </div>
      {actions}
    </div>
  );
};

export default CertificateCard;
