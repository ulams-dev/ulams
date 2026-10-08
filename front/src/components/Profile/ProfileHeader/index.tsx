import React, { ReactNode } from "react";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import styles from "./styles.module.css";

type Props = {
  title: string;
  withTabs?: boolean;
  actions?: ReactNode;
};

const ProfileHeader: React.FC<Props> = ({ title, withTabs, actions }) => {
  return (
    <div className={styles.header}>
      <Title level={2} style={{ marginBottom: 12 }}>
        {title}
      </Title>
      {actions && <div className={styles.actions}>{actions}</div>}
    </div>
  );
};

export default ProfileHeader;
