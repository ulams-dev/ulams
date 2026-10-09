import React, { ReactNode } from "react";
import styles from "./styles.module.css";

type Props = {
  title?: string;
  children: ReactNode;
  icon?: ReactNode;
};

const UserSidebar: React.FC<Props> = ({ children }) => {
  return <div className={styles.sidebar}>{children}</div>;
};

export default UserSidebar;
