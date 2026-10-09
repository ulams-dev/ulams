import React from "react";
import { BreadCrumbs } from "@ulams/components/components/atoms/BreadCrumbs/BreadCrumbs";
import styles from "./styles.module.css";

type Props = {
  items: React.ReactNode[];
};

const Breadcrumbs: React.FC<Props> = ({ items }) => {
  return (
    <div className={styles.breadcrumbs}>
      <BreadCrumbs items={items} />
    </div>
  );
};

export default Breadcrumbs;
