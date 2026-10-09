import React from "react";
import styles from "./styles.module.css";

type Props = React.ComponentPropsWithoutRef<"div">;

const Container: React.FC<Props> = ({ className, ...props }) => (
  <div {...props} className={`${styles.container} ${className ?? ""}`}>
    {props.children}
  </div>
);

export default Container;
