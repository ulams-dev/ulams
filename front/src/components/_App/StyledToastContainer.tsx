import React from "react";
import { ToastContainer, ToastContainerProps } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import styles from "./StyledToastContainer.module.css";

export const StyledToastContainer: React.FC<ToastContainerProps> = (props) => (
  <ToastContainer className={styles.toastContainer} {...props} />
);
