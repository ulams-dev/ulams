import React, { PropsWithChildren } from "react";
import styles from "./styles.module.css";

const CourseCardWrapper: React.FC<PropsWithChildren> = ({ children }) => {
  return <div className={styles.cardWrapper}>{children}</div>;
};

export default CourseCardWrapper;
